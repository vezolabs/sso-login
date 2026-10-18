import React, { useState } from 'react';
import { Card, Switch, Input, Button, Space, Typography, Radio, Tag, message } from 'antd';
import { SaveOutlined, PlusOutlined, UserAddOutlined } from '@ant-design/icons';
import { IdentityProvider } from '../types';

const { Paragraph, Text } = Typography;

interface ProvisioningSettingsProps {
  provider: IdentityProvider;
  onSave: (updatedProvider: IdentityProvider) => Promise<void>;
  loading: boolean;
}

export const ProvisioningSettings: React.FC<ProvisioningSettingsProps> = ({
  provider,
  onSave,
  loading,
}) => {
  const [provisioningEnabled, setProvisioningEnabled] = useState(provider.provisioning_enabled ?? true);
  const [requireMappedRoles, setRequireMappedRoles] = useState(provider.require_mapped_roles_for_creation ?? true);
  const [syncAttributes, setSyncAttributes] = useState(provider.sync_user_attributes_on_login ?? true);
  const [handleDisabled, setHandleDisabled] = useState(provider.handle_disabled_users || 'deny_access');
  const [allowedDomains, setAllowedDomains] = useState<string[]>(provider.allowed_domains || []);
  const [newDomain, setNewDomain] = useState('');

  const handleAddDomain = () => {
    const clean = newDomain.trim().toLowerCase().replace(/^@/, '');
    if (!clean) return;
    if (allowedDomains.includes(clean)) {
      message.warning('Domain already in list.');
      return;
    }
    setAllowedDomains([...allowedDomains, clean]);
    setNewDomain('');
  };

  const handleRemoveDomain = (domain: string) => {
    setAllowedDomains(allowedDomains.filter(d => d !== domain));
  };

  const handleSave = async () => {
    await onSave({
      ...provider,
      provisioning_enabled: provisioningEnabled,
      require_mapped_roles_for_creation: requireMappedRoles,
      sync_user_attributes_on_login: syncAttributes,
      handle_disabled_users: handleDisabled,
      allowed_domains: allowedDomains,
    });
    message.success('User provisioning settings saved.');
  };

  return (
    <Card
      title={<Space><UserAddOutlined /><span>Just-in-Time (JIT) User Provisioning Settings</span></Space>}
      extra={
        <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={loading}>
          Save Settings
        </Button>
      }
      style={{ borderRadius: 8, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}
    >
      <Paragraph style={{ color: '#64748b' }}>
        Configure how users are provisioned and synchronized in Pimcore Studio upon successful SAML authentication.
      </Paragraph>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
        {/* Enable JIT */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid #f1f5f9' }}>
          <div>
            <strong>Enable Automatic User Provisioning (JIT)</strong>
            <div style={{ color: '#64748b', fontSize: 13 }}>
              Automatically create a Pimcore user account when a verified user logs in for the first time.
            </div>
          </div>
          <Switch checked={provisioningEnabled} onChange={val => setProvisioningEnabled(val)} />
        </div>

        {/* Require Mapped Roles */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid #f1f5f9' }}>
          <div>
            <strong>Require Mapped Roles for User Creation</strong>
            <div style={{ color: '#64748b', fontSize: 13 }}>
              Prevent creating accounts if the incoming user has no valid mapped roles in Pimcore.
            </div>
          </div>
          <Switch checked={requireMappedRoles} onChange={val => setRequireMappedRoles(val)} />
        </div>

        {/* Sync User Attributes */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid #f1f5f9' }}>
          <div>
            <strong>Synchronize User Attributes on Every Login</strong>
            <div style={{ color: '#64748b', fontSize: 13 }}>
              Update first name, last name, and email address in Pimcore whenever changed in Microsoft Entra.
            </div>
          </div>
          <Switch checked={syncAttributes} onChange={val => setSyncAttributes(val)} />
        </div>

        {/* Disabled User Policy */}
        <div style={{ padding: '12px 0', borderBottom: '1px solid #f1f5f9' }}>
          <strong>Handling of Disabled Pimcore Users:</strong>
          <div style={{ color: '#64748b', fontSize: 13, marginBottom: 8 }}>
            Determines action if a user exists in Pimcore but has been locally marked inactive.
          </div>
          <Radio.Group value={handleDisabled} onChange={e => setHandleDisabled(e.target.value)}>
            <Radio value="deny_access">Deny Access (Strict — Recommended)</Radio>
            <Radio value="do_nothing">Allow Login</Radio>
          </Radio.Group>
        </div>

        {/* Allowed Email Domains */}
        <div style={{ padding: '12px 0' }}>
          <strong>Allowed Email Domains (Tenant Restriction):</strong>
          <div style={{ color: '#64748b', fontSize: 13, marginBottom: 8 }}>
            Leave empty to allow all domains verified by the IdP, or specify allowed domain names (e.g. <code>company.com</code>).
          </div>
          <Space.Compact style={{ width: 400, marginBottom: 12 }}>
            <Input
              placeholder="e.g. vezolabs.com"
              value={newDomain}
              onChange={e => setNewDomain(e.target.value)}
              onPressEnter={handleAddDomain}
            />
            <Button icon={<PlusOutlined />} onClick={handleAddDomain}>Add Domain</Button>
          </Space.Compact>
          <div>
            {allowedDomains.length === 0 ? (
              <Text type="secondary">All authenticated domains permitted</Text>
            ) : (
              allowedDomains.map(domain => (
                <Tag key={domain} closable onClose={() => handleRemoveDomain(domain)} color="blue" style={{ fontSize: 13, padding: '4px 8px' }}>
                  @{domain}
                </Tag>
              ))
            )}
          </div>
        </div>
      </div>
    </Card>
  );
};
