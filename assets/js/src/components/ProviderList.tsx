import React from 'react';
import { Table, Switch, Button, Space, Tag, Popconfirm, Tooltip } from 'antd';
import {
  EditOutlined,
  ExperimentOutlined,
  DeleteOutlined,
  DownloadOutlined,
  CheckCircleFilled,
  WindowsOutlined,
  SafetyOutlined
} from '@ant-design/icons';
import { IdentityProvider } from '../types';

interface ProviderListProps {
  providers: IdentityProvider[];
  loading: boolean;
  onEdit: (provider: IdentityProvider) => void;
  onToggle: (id: number) => void;
  onDelete: (id: number) => void;
  onTest: (slug: string) => void;
}

export const ProviderList: React.FC<ProviderListProps> = ({
  providers,
  loading,
  onEdit,
  onToggle,
  onDelete,
  onTest,
}) => {
  const columns = [
    {
      title: 'Provider',
      key: 'name',
      render: (_: any, record: IdentityProvider) => (
        <Space orientation="vertical" size={2}>
          <Space>
            {record.type === 'microsoft_entra_id' ? (
              <WindowsOutlined style={{ color: '#0078d4', fontSize: 18 }} />
            ) : (
              <SafetyOutlined style={{ color: '#6366f1', fontSize: 18 }} />
            )}
            <strong style={{ fontSize: 15 }}>{record.display_name}</strong>
            {record.is_default && <Tag color="blue">Default</Tag>}
          </Space>
          <span style={{ color: '#64748b', fontSize: 12 }}>
            Identifier: <code>{record.name}</code> &bull; Type: {record.type === 'microsoft_entra_id' ? 'Microsoft Entra ID' : 'Generic SAML 2.0'}
          </span>
        </Space>
      ),
    },
    {
      title: 'Login Button',
      dataIndex: 'login_button_label',
      key: 'button_label',
      render: (label: string, record: IdentityProvider) => (
        <div>
          <div>{label}</div>
          <small style={{ color: record.login_button_visible ? '#16a34a' : '#94a3b8' }}>
            {record.login_button_visible ? 'Visible on login screen' : 'Hidden'}
          </small>
        </div>
      ),
    },
    {
      title: 'Security & Cert',
      key: 'security',
      render: (_: any, record: IdentityProvider) => (
        <Space direction="vertical" size={2}>
          <div>
            Assertions Signed: {record.want_assertions_signed ? (
              <Tag color="green"><CheckCircleFilled /> Enforced</Tag>
            ) : (
              <Tag color="orange">Optional</Tag>
            )}
          </div>
          {record.idp_x509_cert_rollover && <Tag color="purple">Rollover Cert Configured</Tag>}
        </Space>
      ),
    },
    {
      title: 'Status',
      key: 'enabled',
      render: (_: any, record: IdentityProvider) => (
        <Space>
          <Switch
            checked={record.enabled}
            onChange={() => record.id && onToggle(record.id)}
            checkedChildren="ON"
            unCheckedChildren="OFF"
          />
          <Tag color={record.enabled ? 'success' : 'default'}>
            {record.enabled ? 'Active' : 'Disabled'}
          </Tag>
        </Space>
      ),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: any, record: IdentityProvider) => (
        <Space orientation="horizontal" size="small">
          <Tooltip title="Configure & Edit">
            <Button
              icon={<EditOutlined />}
              onClick={() => onEdit(record)}
            />
          </Tooltip>

          <Tooltip title="Test End-to-End Authentication">
            <Button
              icon={<ExperimentOutlined />}
              onClick={() => onTest(record.name)}
            />
          </Tooltip>

          <Tooltip title="Download Service Provider Metadata XML">
            <Button
              icon={<DownloadOutlined />}
              href={`/sso/metadata/${record.name}`}
              target="_blank"
            />
          </Tooltip>

          <Popconfirm
            title="Delete this Identity Provider?"
            description="Are you sure you want to remove this provider configuration?"
            onConfirm={() => record.id && onDelete(record.id)}
            okText="Yes, Delete"
            cancelText="Cancel"
            okButtonProps={{ danger: true }}
          >
            <Tooltip title="Delete">
              <Button icon={<DeleteOutlined />} danger />
            </Tooltip>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <Table
      dataSource={providers}
      columns={columns}
      rowKey="id"
      loading={loading}
      pagination={false}
      style={{ background: 'white', borderRadius: 8 }}
    />
  );
};
