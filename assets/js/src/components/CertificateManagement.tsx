import React, { useState, useEffect } from 'react';
import { Card, Table, Tag, Button, Space, Typography, Popconfirm, message, Alert } from 'antd';
import {
  SafetyCertificateOutlined,
  ReloadOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  WarningOutlined,
  SwapOutlined
} from '@ant-design/icons';
import { CertificateItem } from '../types';
import { ssoApi } from '../services/api';

const { Paragraph, Text } = Typography;

export const CertificateManagement: React.FC = () => {
  const [certificates, setCertificates] = useState<CertificateItem[]>([]);
  const [loading, setLoading] = useState(false);

  const fetchCerts = async () => {
    setLoading(true);
    try {
      const res = await ssoApi.getCertificates();
      setCertificates(res.certificates);
    } catch (e: any) {
      message.error(`Failed to load certificates: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCerts();
  }, []);

  const handlePromoteRollover = async (providerId: number) => {
    try {
      await ssoApi.rotateCertificate(providerId);
      message.success('Rollover certificate promoted to active signing certificate.');
      fetchCerts();
    } catch (e: any) {
      message.error(`Promotion failed: ${e.message}`);
    }
  };

  const columns = [
    {
      title: 'Provider & Type',
      key: 'provider',
      render: (_: any, record: CertificateItem) => (
        <div>
          <strong>{record.provider_name}</strong>
          <div>
            <Tag color={record.type === 'PRIMARY' ? 'blue' : 'purple'}>
              {record.type} CERTIFICATE
            </Tag>
          </div>
        </div>
      ),
    },
    {
      title: 'Status & Validity',
      key: 'status',
      render: (_: any, record: CertificateItem) => (
        <Space direction="vertical" size={2}>
          <div>
            {record.status === 'VALID' && <Tag color="success"><CheckCircleOutlined /> Valid</Tag>}
            {record.status === 'EXPIRING_SOON' && <Tag color="warning"><ClockCircleOutlined /> Expiring in {record.days_left} days</Tag>}
            {record.status === 'EXPIRED' && <Tag color="error"><WarningOutlined /> Expired</Tag>}
          </div>
          <small style={{ color: '#64748b' }}>
            Valid to: {record.valid_to ? new Date(record.valid_to).toLocaleDateString() : 'N/A'}
          </small>
        </Space>
      ),
    },
    {
      title: 'Issuer & Subject',
      key: 'issuer',
      render: (_: any, record: CertificateItem) => (
        <div style={{ fontSize: 13 }}>
          <div><strong>Subject:</strong> {record.subject}</div>
          <div style={{ color: '#64748b' }}><strong>Issuer:</strong> {record.issuer}</div>
        </div>
      ),
    },
    {
      title: 'Fingerprint (SHA-256)',
      dataIndex: 'fingerprint_sha256',
      key: 'fp',
      render: (fp: string) => <code style={{ fontSize: 11 }}>{fp || 'N/A'}</code>,
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: any, record: CertificateItem) => (
        record.type === 'ROLLOVER' ? (
          <Popconfirm
            title="Promote Rollover Certificate"
            description="Replace the current primary certificate with this rollover certificate?"
            onConfirm={() => handlePromoteRollover(record.provider_id)}
            okText="Promote"
            cancelText="Cancel"
          >
            <Button size="small" type="primary" icon={<SwapOutlined />}>
              Promote to Primary
            </Button>
          </Popconfirm>
        ) : null
      ),
    },
  ];

  return (
    <Card
      title={<Space><SafetyCertificateOutlined /><span>X.509 Signing Certificates & Rollover Management</span></Space>}
      extra={<Button icon={<ReloadOutlined />} onClick={fetchCerts}>Refresh</Button>}
      style={{ borderRadius: 8, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}
    >
      <Paragraph style={{ color: '#64748b' }}>
        Monitor IdP token-signing certificates, expiry timelines, and rollover keys. When Microsoft Entra ID rolls over its signing key,
        both certificates can be active simultaneously to ensure zero downtime.
      </Paragraph>

      <Table
        dataSource={certificates}
        columns={columns}
        rowKey={(r, i) => `${r.provider_id}-${r.type}-${i}`}
        loading={loading}
        pagination={false}
      />
    </Card>
  );
};
