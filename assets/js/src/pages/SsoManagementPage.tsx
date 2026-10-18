import React, { useState, useEffect } from 'react';
import { Tabs, Spin, message, Typography, Card } from 'antd';
import {
  DashboardOutlined,
  SafetyCertificateOutlined,
  WindowsOutlined,
  PartitionOutlined,
  TeamOutlined,
  UserAddOutlined,
  FileTextOutlined,
  BookOutlined
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

const { Title, Paragraph } = Typography;

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
      label: <span><DashboardOutlined /> Overview</span>,
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
      label: <span><SafetyCertificateOutlined /> Identity Providers</span>,
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
      label: <span><WindowsOutlined /> Microsoft Entra Setup Wizard</span>,
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
      label: <span><PartitionOutlined /> Attribute Mapping</span>,
      children: selectedProvider ? (
        <AttributeMappingTable
          provider={selectedProvider}
          onSave={handleSaveProvider}
          loading={loading}
        />
      ) : (
        <p>Please configure a provider first.</p>
      ),
    },
    {
      key: 'roles',
      label: <span><TeamOutlined /> Role Mapping</span>,
      children: selectedProvider ? (
        <RoleMappingTable
          provider={selectedProvider}
          onSave={handleSaveProvider}
          loading={loading}
        />
      ) : (
        <p>Please configure a provider first.</p>
      ),
    },
    {
      key: 'provisioning',
      label: <span><UserAddOutlined /> User Provisioning</span>,
      children: selectedProvider ? (
        <ProvisioningSettings
          provider={selectedProvider}
          onSave={handleSaveProvider}
          loading={loading}
        />
      ) : (
        <p>Please configure a provider first.</p>
      ),
    },
    {
      key: 'certificates',
      label: <span><SafetyCertificateOutlined /> Certificate Management</span>,
      children: <CertificateManagement />,
    },
    {
      key: 'logs',
      label: <span><FileTextOutlined /> Authentication Logs</span>,
      children: <AuditLogTable />,
    },
    {
      key: 'guide',
      label: <span><BookOutlined /> Setup & Recovery Guide</span>,
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
    <div style={{ padding: 24, background: '#f8fafc', minHeight: '100vh' }}>
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={items}
        type="card"
        style={{ background: 'transparent' }}
      />
    </div>
  );
};
