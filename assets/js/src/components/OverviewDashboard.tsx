import React from 'react';
import { Card, Row, Col, Statistic, Alert, Button, Space, Tag } from 'antd';
import {
  SafetyCertificateOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  UserOutlined,
  ClockCircleOutlined,
  PlusOutlined,
  ExperimentOutlined,
  FileTextOutlined,
  BookOutlined,
  WarningOutlined
} from '@ant-design/icons';
import { OverviewMetrics } from '../types';

interface OverviewDashboardProps {
  metrics: OverviewMetrics | null;
  loading: boolean;
  onAddProvider: () => void;
  onTestConfig: () => void;
  onViewLogs: () => void;
  onOpenGuide: () => void;
}

export const OverviewDashboard: React.FC<OverviewDashboardProps> = ({
  metrics,
  loading,
  onAddProvider,
  onTestConfig,
  onViewLogs,
  onOpenGuide,
}) => {
  return (
    <div style={{ padding: '4px 0' }}>
      {/* Top Banner & Quick Actions */}
      <div style={{ marginBottom: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h2 style={{ margin: 0, fontSize: 22, fontWeight: 600 }}>VezoLabs SSO Dashboard</h2>
          <p style={{ margin: '4px 0 0', color: '#64748b' }}>
            Enterprise Single Sign-On management for Microsoft Entra ID & SAML 2.0
          </p>
        </div>
        <Space>
          <Button type="primary" icon={<PlusOutlined />} onClick={onAddProvider}>
            Add Identity Provider
          </Button>
          <Button icon={<ExperimentOutlined />} onClick={onTestConfig}>
            Test Configuration
          </Button>
          <Button icon={<FileTextOutlined />} onClick={onViewLogs}>
            Authentication Logs
          </Button>
          <Button icon={<BookOutlined />} onClick={onOpenGuide}>
            Setup Guide
          </Button>
        </Space>
      </div>

      {/* Warnings Banner */}
      {metrics?.outstanding_warnings && metrics.outstanding_warnings.length > 0 && (
        <Alert
          message="Outstanding Configuration Warnings"
          description={
            <ul style={{ margin: '8px 0 0', paddingLeft: 20 }}>
              {metrics.outstanding_warnings.map((warn, index) => (
                <li key={index}>{warn}</li>
              ))}
            </ul>
          }
          type="warning"
          showIcon
          icon={<WarningOutlined />}
          style={{ marginBottom: 20 }}
        />
      )}

      {/* Metrics Grid */}
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} md={6}>
          <Card loading={loading} bordered={false} style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
            <Statistic
              title="SSO System Status"
              value={metrics?.sso_enabled ? 'Active' : 'Disabled'}
              valueStyle={{ color: metrics?.sso_enabled ? '#16a34a' : '#94a3b8', fontWeight: 600 }}
              prefix={metrics?.sso_enabled ? <CheckCircleOutlined /> : <CloseCircleOutlined />}
            />
            <div style={{ marginTop: 8 }}>
              Health: <Tag color={metrics?.health_status === 'HEALTHY' ? 'success' : metrics?.health_status === 'WARNING' ? 'warning' : 'error'}>
                {metrics?.health_status || 'UNKNOWN'}
              </Tag>
            </div>
          </Card>
        </Col>

        <Col xs={24} sm={12} md={6}>
          <Card loading={loading} bordered={false} style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
            <Statistic
              title="Configured Providers"
              value={metrics?.providers_enabled ?? 0}
              suffix={`/ ${metrics?.providers_total ?? 0}`}
              prefix={<SafetyCertificateOutlined style={{ color: '#0284c7' }} />}
              valueStyle={{ fontWeight: 600 }}
            />
            <div style={{ marginTop: 8, color: '#64748b', fontSize: 13 }}>
              Default: <strong>{metrics?.active_provider?.display_name || 'None'}</strong>
            </div>
          </Card>
        </Col>

        <Col xs={24} sm={12} md={6}>
          <Card loading={loading} bordered={false} style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
            <Statistic
              title="SSO Managed Users"
              value={metrics?.sso_users_count ?? 0}
              prefix={<UserOutlined style={{ color: '#8b5cf6' }} />}
              valueStyle={{ fontWeight: 600 }}
            />
            <div style={{ marginTop: 8, color: '#64748b', fontSize: 13 }}>
              JIT auto-provisioned accounts
            </div>
          </Card>
        </Col>

        <Col xs={24} sm={12} md={6}>
          <Card loading={loading} bordered={false} style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
            <Statistic
              title="Last Successful Login"
              value={metrics?.last_successful_login ? new Date(metrics.last_successful_login).toLocaleTimeString() : 'None'}
              prefix={<ClockCircleOutlined style={{ color: '#10b981' }} />}
              valueStyle={{ fontSize: 18, fontWeight: 600 }}
            />
            <div style={{ marginTop: 8, color: '#64748b', fontSize: 12 }}>
              {metrics?.last_successful_login ? new Date(metrics.last_successful_login).toLocaleDateString() : 'No logins recorded yet'}
            </div>
          </Card>
        </Col>
      </Row>
    </div>
  );
};
