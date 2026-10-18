import React, { useState, useEffect } from 'react';
import { Tabs, Spin, message, Typography, Card, Space, ConfigProvider, Button, Tag } from 'antd';
import {
  DashboardOutlined,
  SafetyCertificateOutlined,
  WindowsOutlined,
  PartitionOutlined,
  TeamOutlined,
  UserAddOutlined,
  FileTextOutlined,
  BookOutlined,
  KeyOutlined,
  SyncOutlined,
} from '@ant-design/icons';
import { OverviewDashboard } from '../components/OverviewDashboard';
import { ProviderList } from '../components/ProviderList';
import { EntraIdWizard } from '../components/EntraIdWizard';
import { AttributeMappingTable } from '../components/AttributeMappingTable';
import { RoleMappingTable } from '../components/RoleMappingTable';
import { ProvisioningSettings } from '../components/ProvisioningSettings';
import { CertificateManagement } from '../components/CertificateManagement';
import { AuditLogTable } from '../components/AuditLogTable';
import { IdentityProvider, OverviewMetrics } from '../types';
import { ssoApi } from '../services/api';

const { Title, Text, Paragraph } = Typography;

const PIMCORE_THEME = {
  token: {
    colorPrimary: '#722ed1',
    borderRadius: 6,
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
  },
};

export const SsoManagementPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState('overview');
  const [loading, setLoading] = useState(false);
  const [metrics, setMetrics] = useState<OverviewMetrics | null>(null);
  const [providers, setProviders] = useState<IdentityProvider[]>([]);
  const [selectedProvider, setSelectedProvider] = useState<IdentityProvider | null>(null);

  const loadData = async () => {
    setLoading(true);
    try {
      const [overviewData, providersData] = await Promise.all([
        ssoApi.getOverview(),
        ssoApi.getProviders(),
      ]);
      setMetrics(overviewData);
      setProviders(providersData.items);
      if (providersData.items.length > 0 && !selectedProvider) {
        setSelectedProvider(providersData.items[0]);
      }
    } catch (e: any) {
      message.error(`Failed to load SSO data: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleToggle = async (id: number) => {
    try {
      const res = await ssoApi.toggleProvider(id);
      message.success(res.message);
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await ssoApi.deleteProvider(id);
      message.success('Provider deleted.');
      loadData();
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleSaveProvider = async (updated: IdentityProvider) => {
    if (!updated.id) return;
    try {
      const res = await ssoApi.updateProvider(updated.id, updated);
      setSelectedProvider(res.provider);
      loadData();
    } catch (e: any) {
      message.error(`Save failed: ${e.message}`);
      throw e;
    }
  };

  const items = [
    {
      key: 'overview',
      label: (
        <Space size={6}>
          <DashboardOutlined />
          <span>Overview</span>
        </Space>
      ),
      children: (
        <OverviewDashboard
          metrics={metrics}
          loading={loading}
          onAddProvider={() => setActiveTab('wizard')}
          onTestConfig={() => {
            if (selectedProvider) {
              window.open(`/sso/test/${selectedProvider.name}`, '_blank');
            } else {
              message.warning('No provider selected to test.');
            }
          }}
          onViewLogs={() => setActiveTab('logs')}
          onOpenGuide={() => setActiveTab('guide')}
        />
      ),
    },
    {
      key: 'providers',
      label: (
        <Space size={6}>
          <SafetyCertificateOutlined />
          <span>Identity Providers</span>
        </Space>
      ),
      children: (
        <ProviderList
          providers={providers}
          loading={loading}
          onEdit={(p) => {
            setSelectedProvider(p);
            setActiveTab('wizard');
          }}
          onToggle={handleToggle}
          onDelete={handleDelete}
          onTest={(slug) => window.open(`/sso/test/${slug}`, '_blank')}
        />
      ),
    },
    {
      key: 'wizard',
      label: (
        <Space size={6}>
          <WindowsOutlined />
          <span>Setup Wizard</span>
        </Space>
      ),
      children: (
        <EntraIdWizard
          initialProvider={selectedProvider}
          onFinished={() => {
            loadData();
            setActiveTab('overview');
          }}
          onCancel={() => setActiveTab('overview')}
        />
      ),
    },
    {
      key: 'attributes',
      label: (
        <Space size={6}>
          <PartitionOutlined />
          <span>Attribute Mapping</span>
        </Space>
      ),
      children: selectedProvider ? (
        <AttributeMappingTable
          provider={selectedProvider}
          onSave={handleSaveProvider}
          loading={loading}
        />
      ) : (
        <Card style={{ borderRadius: 8 }}>
          <p>Please configure a provider first.</p>
        </Card>
      ),
    },
    {
      key: 'roles',
      label: (
        <Space size={6}>
          <TeamOutlined />
          <span>Role Mapping</span>
        </Space>
      ),
      children: selectedProvider ? (
        <RoleMappingTable
          provider={selectedProvider}
          onSave={handleSaveProvider}
          loading={loading}
        />
      ) : (
        <Card style={{ borderRadius: 8 }}>
          <p>Please configure a provider first.</p>
        </Card>
      ),
    },
    {
      key: 'provisioning',
      label: (
        <Space size={6}>
          <UserAddOutlined />
          <span>User Provisioning</span>
        </Space>
      ),
      children: selectedProvider ? (
        <ProvisioningSettings
          provider={selectedProvider}
          onSave={handleSaveProvider}
          loading={loading}
        />
      ) : (
        <Card style={{ borderRadius: 8 }}>
          <p>Please configure a provider first.</p>
        </Card>
      ),
    },
    {
      key: 'certificates',
      label: (
        <Space size={6}>
          <SafetyCertificateOutlined />
          <span>Certificates</span>
        </Space>
      ),
      children: <CertificateManagement />,
    },
    {
      key: 'logs',
      label: (
        <Space size={6}>
          <FileTextOutlined />
          <span>Audit Logs</span>
        </Space>
      ),
      children: <AuditLogTable />,
    },
    {
      key: 'guide',
      label: (
        <Space size={6}>
          <BookOutlined />
          <span>Setup Guide</span>
        </Space>
      ),
      children: (
        <Card style={{ borderRadius: 8 }}>
          <Title level={4}>Emergency Recovery & Local Login Access</Title>
          <Paragraph>
            If Microsoft Entra ID is experiencing an outage or an invalid configuration locks users out,
            authorized administrators can always access Pimcore Studio via standard credentials:
          </Paragraph>
          <pre style={{ background: '#0f172a', color: '#e2e8f0', padding: 16, borderRadius: 6 }}>
            {`Direct Local Login URL:\n${window.location.origin}/pimcore-studio/login?local=1\n\nDefault local admin credentials:\nUsername: admin\nPassword: (local admin password)`}
          </pre>
          <Paragraph>
            Appending <code>?local=1</code> forces display of the local username/password login form and prevents automatic SSO redirection.
          </Paragraph>
        </Card>
      ),
    },
  ];

  return (
    <ConfigProvider theme={PIMCORE_THEME}>
      <div
        style={{
          height: '100%',
          width: '100%',
          overflowY: 'auto',
          background: '#f8fafc',
          padding: '24px',
          boxSizing: 'border-box',
        }}
      >
        {/* Page Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
          <Space align="center" size={14}>
            <div
              style={{
                width: 44,
                height: 44,
                borderRadius: 10,
                background: '#f3e8ff',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#722ed1',
                fontSize: 24,
                boxShadow: '0 2px 6px rgba(114, 46, 209, 0.15)',
              }}
            >
              <KeyOutlined />
            </div>
            <div>
              <Title level={3} style={{ margin: 0, color: '#1e293b' }}>
                Vezo Labs : Single Sign-On (SSO)
              </Title>
              <Text type="secondary" style={{ fontSize: 13 }}>
                Enterprise authentication management for Microsoft Entra ID and SAML 2.0 in Pimcore Studio
              </Text>
            </div>
          </Space>

          <Space>
            <Button icon={<SyncOutlined />} onClick={loadData} loading={loading}>
              Refresh
            </Button>
          </Space>
        </div>

        {/* Main Tabs */}
        <Tabs
          activeKey={activeTab}
          onChange={setActiveTab}
          items={items}
          size="middle"
          destroyInactiveTabPane={false}
          style={{ background: 'transparent' }}
        />

        {/* Copyright Footer */}
        <div style={{ marginTop: 32, padding: '16px 0', borderTop: '1px solid #e2e8f0', textAlign: 'center', color: '#64748b', fontSize: 13 }}>
          <span>© {new Date().getFullYear()} <a href="https://vezolabs.com/" target="_blank" rel="noopener noreferrer" style={{ color: '#722ed1', fontWeight: 600 }}>VezoLabs</a>. All rights reserved. | </span>
          <a href="https://vezolabs.com/plugins/pimcore-sso-login" target="_blank" rel="noopener noreferrer" style={{ color: '#475569', textDecoration: 'underline' }}>
            Plugin Details & Demo
          </a>
          <span> · </span>
          <a href="https://vezolabs.com/contact" target="_blank" rel="noopener noreferrer" style={{ color: '#475569', textDecoration: 'underline' }}>
            Contact & Support
          </a>
        </div>
      </div>
    </ConfigProvider>
  );
};
