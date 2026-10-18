import React, { useState } from 'react';
import { Card, Table, Input, Button, Space, Typography, Tag, message } from 'antd';
import { PlusOutlined, DeleteOutlined, ReloadOutlined, SaveOutlined } from '@ant-design/icons';
import { IdentityProvider } from '../types';

const { Title, Paragraph, Text } = Typography;

interface AttributeMappingTableProps {
  provider: IdentityProvider;
  onSave: (updatedProvider: IdentityProvider) => Promise<void>;
  loading: boolean;
}

export const AttributeMappingTable: React.FC<AttributeMappingTableProps> = ({
  provider,
  onSave,
  loading,
}) => {
  const [mapping, setMapping] = useState<Record<string, string>>(provider.attribute_mapping || {});
  const [newField, setNewField] = useState('');
  const [newClaim, setNewClaim] = useState('');

  const defaultEntraClaims: Record<string, string> = {
    email: 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
    firstname: 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
    lastname: 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
    displayname: 'http://schemas.microsoft.com/identity/claims/displayname',
    external_id: 'http://schemas.microsoft.com/identity/claims/objectidentifier',
  };

  const handleResetToEntraDefaults = () => {
    setMapping(defaultEntraClaims);
    message.info('Reset to Microsoft Entra ID standard claim mappings.');
  };

  const handleAddCustom = () => {
    if (!newField.trim() || !newClaim.trim()) {
      message.error('Please enter both Pimcore Field and SAML Claim URI.');
      return;
    }
    setMapping(prev => ({ ...prev, [newField.trim().toLowerCase()]: newClaim.trim() }));
    setNewField('');
    setNewClaim('');
  };

  const handleDelete = (fieldKey: string) => {
    const copy = { ...mapping };
    delete copy[fieldKey];
    setMapping(copy);
  };

  const handleSave = async () => {
    await onSave({ ...provider, attribute_mapping: mapping });
    message.success('Attribute mappings saved successfully.');
  };

  const dataSource = Object.entries(mapping).map(([field, claim]) => ({
    key: field,
    field,
    claim,
    isMandatory: field === 'email' || field === 'external_id',
  }));

  const columns = [
    {
      title: 'Pimcore User Field',
      dataIndex: 'field',
      key: 'field',
      render: (field: string, record: any) => (
        <Space>
          <strong>{field}</strong>
          {record.isMandatory && <Tag color="red">Required</Tag>}
        </Space>
      ),
    },
    {
      title: 'Incoming SAML Claim URI / Attribute Name',
      dataIndex: 'claim',
      key: 'claim',
      render: (claim: string, record: any) => (
        <Input
          value={claim}
          onChange={e => {
            const val = e.target.value;
            setMapping(prev => ({ ...prev, [record.field]: val }));
          }}
          placeholder="e.g. http://schemas.xmlsoap.org/... or email"
        />
      ),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: any, record: any) => (
        !record.isMandatory ? (
          <Button
            danger
            icon={<DeleteOutlined />}
            size="small"
            onClick={() => handleDelete(record.field)}
          />
        ) : null
      ),
    },
  ];

  return (
    <Card
      title="SAML Attribute & Claim Mappings"
      extra={
        <Space>
          <Button icon={<ReloadOutlined />} onClick={handleResetToEntraDefaults}>
            Reset to Entra Defaults
          </Button>
          <Button type="primary" icon={<SaveOutlined />} onClick={handleSave} loading={loading}>
            Save Mappings
          </Button>
        </Space>
      }
      style={{ borderRadius: 8, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}
    >
      <Paragraph style={{ color: '#64748b' }}>
        Map incoming SAML claims to Pimcore user properties. A stable tenant-scoped identifier
        (such as Microsoft Entra <code>objectidentifier</code>) is used as the primary identity key.
      </Paragraph>

      <Table
        dataSource={dataSource}
        columns={columns}
        pagination={false}
        style={{ marginBottom: 20 }}
      />

      <div style={{ background: '#f8fafc', padding: 16, borderRadius: 6, border: '1px solid #e2e8f0' }}>
        <Text strong>Add Custom Attribute Mapping</Text>
        <div style={{ display: 'flex', gap: 12, marginTop: 8 }}>
          <Input
            placeholder="Pimcore field (e.g. department)"
            value={newField}
            onChange={e => setNewField(e.target.value)}
            style={{ width: '35%' }}
          />
          <Input
            placeholder="SAML Claim URI / attribute name"
            value={newClaim}
            onChange={e => setNewClaim(e.target.value)}
            style={{ width: '50%' }}
          />
          <Button icon={<PlusOutlined />} onClick={handleAddCustom}>
            Add Mapping
          </Button>
        </div>
      </div>
    </Card>
  );
};
