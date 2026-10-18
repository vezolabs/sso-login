import React, { useState, useEffect } from 'react';
import {
  Card,
  Table,
  Input,
  Select,
  Button,
  Space,
  Typography,
  Switch,
  Radio,
  Divider,
  message,
  Alert
} from 'antd';
import { PlusOutlined, DeleteOutlined, SaveOutlined, SafetyOutlined } from '@ant-design/icons';
import { IdentityProvider, PimcoreRole } from '../types';
import { ssoApi } from '../services/api';

const { Paragraph, Text } = Typography;

interface RoleMappingTableProps {
  provider: IdentityProvider;
  onSave: (updatedProvider: IdentityProvider) => Promise<void>;
  loading: boolean;
}

export const RoleMappingTable: React.FC<RoleMappingTableProps> = ({
  provider,
  onSave,
  loading,
}) => {
  const [roleMapping, setRoleMapping] = useState<Record<string, string>>(provider.role_mapping || {});
  const [roleSyncPolicy, setRoleSyncPolicy] = useState(provider.role_sync_policy || 'every_login');
  const [unmappedPolicy, setUnmappedPolicy] = useState(provider.unmapped_role_policy || 'deny');
  const [fallbackRole, setFallbackRole] = useState(provider.restricted_fallback_role || '');
  const [revokeRemoved, setRevokeRemoved] = useState(provider.revoke_removed_roles ?? true);

  const [availableRoles, setAvailableRoles] = useState<PimcoreRole[]>([]);
  const [rolesLoading, setRolesLoading] = useState(false);

  const [newExtRole, setNewExtRole] = useState('');
  const [newTargetRole, setNewTargetRole] = useState('');

  useEffect(() => {
    setRolesLoading(true);
    ssoApi.getPimcoreRoles()
      .then(res => setAvailableRoles(res.roles))
      .catch(err => message.error(`Failed to load Pimcore roles: ${err.message}`))
      .finally(() => setRolesLoading(false));
  }, []);

  const handleAddMapping = () => {
    if (!newExtRole.trim() || !newTargetRole) {
      message.error('Please enter external role/group and select a target Pimcore role.');
      return;
    }
    setRoleMapping(prev => ({ ...prev, [newExtRole.trim()]: newTargetRole }));
    setNewExtRole('');
    setNewTargetRole('');
  };

  const handleDelete = (extRole: string) => {
    const copy = { ...roleMapping };
    delete copy[extRole];
    setRoleMapping(copy);
  };

  const handleSave = async () => {
    await onSave({
      ...provider,
      role_mapping: roleMapping,
      role_sync_policy: roleSyncPolicy,
      unmapped_role_policy: unmappedPolicy,
      restricted_fallback_role: fallbackRole || null,
      revoke_removed_roles: revokeRemoved,
    });
    message.success('Role mapping and synchronization policies saved successfully.');
  };

  const dataSource = Object.entries(roleMapping).map(([extRole, targetRole]) => ({
    key: extRole,
    extRole,
    targetRole,
  }));

  const columns = [
    {
      title: 'Microsoft Entra Application Role or Group Object ID',
      dataIndex: 'extRole',
      key: 'extRole',
      render: (role: string) => <code>{role}</code>,
    },
    {
      title: 'Target Pimcore Role',
      dataIndex: 'targetRole',
      key: 'targetRole',
      render: (target: string, record: any) => (
        <Select
          style={{ width: '100%' }}
          value={target}
          onChange={val => {
            setRoleMapping(prev => ({ ...prev, [record.extRole]: val }));
          }}
          options={availableRoles.map(r => ({ label: r.name, value: r.name }))}
        />
      ),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: any, record: any) => (
        <Button
          danger
          icon={<DeleteOutlined />}
          size="small"
          onClick={() => handleDelete(record.extRole)}
        />
      ),
    },
  ];

  return (
    <Card
      title="SSO Role Mapping & Authorization Policies"
      extra={
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={loading}>
          Save Role Policies
        </Button>
      }
      style={{ borderRadius: 8, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}
    >
      <Paragraph style={{ color: '#64748b' }}>
        Map Microsoft Entra application roles (e.g. <code>pim_admin</code>) or Azure Group Object IDs
        directly to existing Pimcore roles. No code or YAML modifications required.
      </Paragraph>

      <Table
        dataSource={dataSource}
        columns={columns}
        pagination={false}
        loading={rolesLoading}
        style={{ marginBottom: 20 }}
      />

      <div style={{ background: '#f8fafc', padding: 16, borderRadius: 6, border: '1px solid #e2e8f0', marginBottom: 24 }}>
        <Text strong>Add Role Mapping</Text>
        <div style={{ display: 'flex', gap: 12, marginTop: 8 }}>
          <Input
            placeholder="Entra Role Name or Group Object ID (e.g. pim_admin)"
            value={newExtRole}
            onChange={e => setNewExtRole(e.target.value)}
            style={{ width: '50%' }}
          />
          <Select
            placeholder="Select target Pimcore Role"
            value={newTargetRole || undefined}
            onChange={val => setNewTargetRole(val)}
            options={availableRoles.map(r => ({ label: r.name, value: r.name }))}
            style={{ width: '40%' }}
          />
          <Button icon={<PlusOutlined />} onClick={handleAddMapping}>
            Add Mapping
          </Button>
        </div>
      </div>

      <Divider />

      <Title level={5}><SafetyOutlined /> Role Synchronization Policies</Title>

      <div style={{ marginBottom: 20 }}>
        <Text strong>Role Synchronization Timing:</Text>
        <div style={{ marginTop: 8 }}>
          <Radio.Group value={roleSyncPolicy} onChange={e => setRoleSyncPolicy(e.target.value)}>
            <Radio value="every_login">Synchronize roles on every login (Strict policy)</Radio>
            <Radio value="first_login_only">Provision roles on first login only</Radio>
          </Radio.Group>
        </div>
      </div>

      <div style={{ marginBottom: 20 }}>
        <Text strong>Unmapped External Roles Policy:</Text>
        <div style={{ marginTop: 8 }}>
          <Radio.Group value={unmappedPolicy} onChange={e => setUnmappedPolicy(e.target.value)}>
            <Radio value="deny">Deny Access (Default — safest)</Radio>
            <Radio value="assign_restricted">Assign Restricted Fallback Role</Radio>
            <Radio value="manual_approval">Require Manual Approval</Radio>
          </Radio.Group>
        </div>
      </div>

      {unmappedPolicy === 'assign_restricted' && (
        <div style={{ background: '#fffbeb', padding: 16, borderRadius: 6, marginBottom: 20, border: '1px solid #fef3c7' }}>
          <Text strong style={{ color: '#b45309' }}>Restricted Fallback Role:</Text>
          <div style={{ marginTop: 8 }}>
            <Select
              style={{ width: 300 }}
              placeholder="Select fallback role"
              value={fallbackRole || undefined}
              onChange={val => setFallbackRole(val)}
              options={availableRoles.map(r => ({ label: r.name, value: r.name }))}
            />
          </div>
          <small style={{ color: '#92400e', display: 'block', marginTop: 4 }}>
            Caution: Never select an administrative role as the fallback role.
          </small>
        </div>
      )}

      <div>
        <Space direction="horizontal" align="center">
          <Switch checked={revokeRemoved} onChange={val => setRevokeRemoved(val)} />
          <div>
            <strong>Revoke Removed External Roles on Login</strong>
            <div style={{ color: '#64748b', fontSize: 13 }}>
              If a role is removed in Microsoft Entra, automatically remove the corresponding SSO role in Pimcore on next login.
              (Locally assigned administrator roles are never revoked).
            </div>
          </div>
        </Space>
      </div>
    </Card>
  );
};
