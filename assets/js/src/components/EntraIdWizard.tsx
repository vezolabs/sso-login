import React, { useState, useEffect } from 'react';
import {
  Steps,
  Button,
  Card,
  Input,
  Radio,
  Upload,
  Alert,
  Form,
  Space,
  Typography,
  message,
  Divider,
  Tag,
  Tooltip
} from 'antd';
import {
  WindowsOutlined,
  CopyOutlined,
  CheckCircleOutlined,
  UploadOutlined,
  LinkOutlined,
  FormOutlined,
  ExperimentOutlined,
  ArrowRightOutlined,
  ArrowLeftOutlined,
  SaveOutlined
} from '@ant-design/icons';
import { IdentityProvider, ValidationResult } from '../types';
import { ssoApi } from '../services/api';

const { Title, Text, Paragraph } = Typography;
const { TextArea } = Input;

interface EntraIdWizardProps {
  initialProvider?: IdentityProvider | null;
  onFinished: () => void;
  onCancel: () => void;
}

export const EntraIdWizard: React.FC<EntraIdWizardProps> = ({
  initialProvider,
  onFinished,
  onCancel,
}) => {
  const [currentStep, setCurrentStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);

  // Form State
  const [provider, setProvider] = useState<Partial<IdentityProvider>>({
    name: initialProvider?.name || 'entra-id',
    type: 'microsoft_entra_id',
    display_name: initialProvider?.display_name || 'Microsoft Entra ID',
    description: initialProvider?.description || 'Azure Single Sign-On for Pimcore Studio',
    enabled: initialProvider?.enabled || false,
    is_default: initialProvider?.is_default ?? true,
    tenant_id: initialProvider?.tenant_id || '',
    idp_entity_id: initialProvider?.idp_entity_id || '',
    idp_sso_url: initialProvider?.idp_sso_url || '',
    idp_slo_url: initialProvider?.idp_slo_url || '',
    idp_x509_cert: initialProvider?.idp_x509_cert || '',
    idp_x509_cert_rollover: initialProvider?.idp_x509_cert_rollover || '',
    idp_metadata_url: initialProvider?.idp_metadata_url || '',
    idp_metadata_xml: initialProvider?.idp_metadata_xml || '',
    name_id_format: initialProvider?.name_id_format || 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    want_assertions_signed: initialProvider?.want_assertions_signed ?? true,
    login_button_label: initialProvider?.login_button_label || 'Sign in with Microsoft',
    login_button_visible: initialProvider?.login_button_visible ?? true,
    login_button_icon: 'microsoft',
    attribute_mapping: initialProvider?.attribute_mapping || {
      email: 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
      firstname: 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
      lastname: 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
      displayname: 'http://schemas.microsoft.com/identity/claims/displayname',
      external_id: 'http://schemas.microsoft.com/identity/claims/objectidentifier',
    },
    role_mapping: initialProvider?.role_mapping || {},
    role_sync_policy: initialProvider?.role_sync_policy || 'every_login',
    unmapped_role_policy: initialProvider?.unmapped_role_policy || 'deny',
    revoke_removed_roles: initialProvider?.revoke_removed_roles ?? true,
    provisioning_enabled: initialProvider?.provisioning_enabled ?? true,
    require_mapped_roles_for_creation: initialProvider?.require_mapped_roles_for_creation ?? true,
    sync_user_attributes_on_login: initialProvider?.sync_user_attributes_on_login ?? true,
    handle_disabled_users: initialProvider?.handle_disabled_users || 'deny_access',
    allowed_domains: initialProvider?.allowed_domains || [],
  });

  const [idpConfigMethod, setIdpConfigMethod] = useState<'url' | 'xml' | 'manual'>('url');
  const [metadataUrlInput, setMetadataUrlInput] = useState(initialProvider?.idp_metadata_url || '');
  const [metadataXmlInput, setMetadataXmlInput] = useState(initialProvider?.idp_metadata_xml || '');

  // Computed Service Provider details
  const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8088';
  const spIdentifier = provider.sp_entity_id || `${origin}/sso/metadata/${provider.name || 'entra-id'}`;
  const spAcsUrl = provider.sp_acs_url || `${origin}/sso/acs/${provider.name || 'entra-id'}`;
  const spSignOnUrl = `${origin}/sso/login/${provider.name || 'entra-id'}`;
  const spMetadataUrl = `${origin}/sso/metadata/${provider.name || 'entra-id'}`;

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    message.success(`Copied ${label} to clipboard!`);
  };

  // Step 4: Import via URL
  const handleFetchUrl = async () => {
    if (!metadataUrlInput) {
      message.error('Please enter the App Federation Metadata URL from Azure.');
      return;
    }
    setLoading(true);
    try {
      const res = await ssoApi.fetchMetadataUrl(metadataUrlInput);
      setProvider(prev => ({
        ...prev,
        idp_entity_id: res.parsed.entity_id,
        idp_sso_url: res.parsed.sso_url,
        idp_slo_url: res.parsed.slo_url || '',
        idp_x509_cert: res.parsed.primary_cert,
        idp_x509_cert_rollover: res.parsed.rollover_cert || '',
        idp_metadata_url: metadataUrlInput,
        idp_metadata_xml: res.raw_xml,
      }));
      message.success('Federation metadata fetched and verified successfully!');
    } catch (e: any) {
      message.error(`Failed to fetch metadata: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  // Step 4: Import via XML Upload/Paste
  const handleParseXml = async (xmlString: string) => {
    if (!xmlString.trim()) {
      message.error('Please provide XML content.');
      return;
    }
    setLoading(true);
    try {
      const res = await ssoApi.parseXml(xmlString);
      setProvider(prev => ({
        ...prev,
        idp_entity_id: res.parsed.entity_id,
        idp_sso_url: res.parsed.sso_url,
        idp_slo_url: res.parsed.slo_url || '',
        idp_x509_cert: res.parsed.primary_cert,
        idp_x509_cert_rollover: res.parsed.rollover_cert || '',
        idp_metadata_xml: xmlString,
      }));
      message.success('XML metadata parsed successfully!');
    } catch (e: any) {
      message.error(`Failed to parse XML: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  // Step 5: Save & Validate
  const handleSaveAndValidate = async () => {
    setLoading(true);
    try {
      let savedProvider: IdentityProvider;
      if (initialProvider?.id) {
        const res = await ssoApi.updateProvider(initialProvider.id, provider);
        savedProvider = res.provider;
      } else {
        const res = await ssoApi.createProvider(provider);
        savedProvider = res.provider;
      }
      setProvider(savedProvider);

      // Fetch validation result
      const valRes = await ssoApi.getProvider(savedProvider.id!);
      setValidationResult(valRes.validation);

      if (valRes.validation.valid) {
        message.success('Configuration validated successfully!');
      } else {
        message.warning('Configuration saved, but some security checks failed.');
      }
    } catch (e: any) {
      message.error(`Save failed: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  // Step 7: Activate Provider
  const handleActivate = async () => {
    if (!provider.id) return;
    setLoading(true);
    try {
      await ssoApi.updateProvider(provider.id, { ...provider, enabled: true });
      message.success('Microsoft Entra ID SSO is now ACTIVE!');
      onFinished();
    } catch (e: any) {
      message.error(`Activation failed: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  const steps = [
    { title: 'Provider', description: 'Select IdP' },
    { title: 'Azure App', description: 'Create in Entra' },
    { title: 'SP Details', description: 'Pimcore URLs' },
    { title: 'IdP Metadata', description: 'Import Azure info' },
    { title: 'Validate', description: 'Checklist' },
    { title: 'Test Login', description: 'End-to-End' },
    { title: 'Activate', description: 'Enable SSO' },
  ];

  return (
    <Card
      style={{ maxWidth: 960, margin: '0 auto', boxShadow: '0 4px 12px rgba(0,0,0,0.06)', borderRadius: 12 }}
      title={
        <Space>
          <WindowsOutlined style={{ color: '#0078d4', fontSize: 22 }} />
          <span>Microsoft Entra ID Setup Wizard</span>
        </Space>
      }
      extra={
        <Button onClick={onCancel}>Cancel</Button>
      }
    >
      <Steps current={currentStep} items={steps} style={{ marginBottom: 32 }} />

      {/* STEP 0: Select Provider */}
      {currentStep === 0 && (
        <div>
          <Title level={4}>Step 1 — Provider Selection</Title>
          <Paragraph>
            This wizard guides you through setting up enterprise Single Sign-On (SSO) between
            <strong> Microsoft Entra ID</strong> (formerly Azure Active Directory) and <strong>Pimcore 12 Studio</strong> using SAML 2.0.
          </Paragraph>

          <Card style={{ background: '#f8fafc', marginBottom: 24, border: '1px solid #e2e8f0' }}>
            <Radio.Group value={provider.type} disabled>
              <Radio value="microsoft_entra_id">
                <Space>
                  <WindowsOutlined style={{ color: '#0078d4', fontSize: 18 }} />
                  <strong>Microsoft Entra ID</strong> (Recommended for Microsoft 365 / Azure tenants)
                </Space>
              </Radio>
            </Radio.Group>
          </Card>

          <Form layout="vertical">
            <Form.Item label="Provider Identifier (Slug)" required help="Unique URL slug used in SSO endpoints">
              <Input
                value={provider.name}
                onChange={e => setProvider({ ...provider, name: e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '') })}
                placeholder="entra-id"
              />
            </Form.Item>
            <Form.Item label="Display Name" required>
              <Input
                value={provider.display_name}
                onChange={e => setProvider({ ...provider, display_name: e.target.value })}
                placeholder="Microsoft Entra ID"
              />
            </Form.Item>
          </Form>
        </div>
      )}

      {/* STEP 1: Azure Portal Guide */}
      {currentStep === 1 && (
        <div>
          <Title level={4}>Step 2 — Create Enterprise Application in Microsoft Entra</Title>
          <Paragraph>
            Follow these simple steps in the Microsoft Entra admin center:
          </Paragraph>

          <div style={{ background: '#f8fafc', padding: 20, borderRadius: 8, border: '1px solid #e2e8f0', marginBottom: 24 }}>
            <ol style={{ paddingLeft: 20, lineHeight: 1.8 }}>
              <li>Open the <a href="https://entra.microsoft.com" target="_blank" rel="noreferrer">Microsoft Entra admin center</a>.</li>
              <li>In the left sidebar, navigate to <strong>Identity &rarr; Applications &rarr; Enterprise applications</strong>.</li>
              <li>Click <strong>+ New application</strong> at the top.</li>
              <li>Click <strong>Create your own application</strong>.</li>
              <li>Enter a name such as <code>Pimcore Studio</code>.</li>
              <li>Select <strong>Integrate any other application you don't find in the gallery (Non-gallery)</strong>.</li>
              <li>Click <strong>Create</strong> at the bottom.</li>
              <li>Once created, select <strong>Single sign-on</strong> from the application's left menu, then select <strong>SAML</strong>.</li>
            </ol>
          </div>

          <Alert
            message="Keep the Azure tab open"
            description="In the next step, you will paste Pimcore's Service Provider values into the 'Basic SAML Configuration' box in Azure."
            type="info"
            showIcon
          />
        </div>
      )}

      {/* STEP 2: SP Details (Copyable Values) */}
      {currentStep === 2 && (
        <div>
          <Title level={4}>Step 3 — Pimcore Service Provider Details</Title>
          <Paragraph>
            In your Azure SAML configuration, click <strong>Edit</strong> on <strong>1. Basic SAML Configuration</strong> and copy these exact values:
          </Paragraph>

          <Card style={{ background: '#f8fafc', border: '1px solid #e2e8f0', marginBottom: 20 }}>
            {/* SP Entity ID */}
            <div style={{ marginBottom: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                <Text strong>Identifier (Entity ID)</Text>
                <Button size="small" icon={<CopyOutlined />} onClick={() => copyToClipboard(spIdentifier, 'Identifier')}>
                  Copy
                </Button>
              </div>
              <Input readOnly value={spIdentifier} style={{ background: 'white' }} />
            </div>

            {/* ACS URL */}
            <div style={{ marginBottom: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                <Text strong>Reply URL (Assertion Consumer Service URL)</Text>
                <Button size="small" icon={<CopyOutlined />} onClick={() => copyToClipboard(spAcsUrl, 'Reply URL')}>
                  Copy
                </Button>
              </div>
              <Input readOnly value={spAcsUrl} style={{ background: 'white' }} />
            </div>

            {/* Sign-on URL */}
            <div style={{ marginBottom: 16 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                <Text strong>Sign-on URL (Optional)</Text>
                <Button size="small" icon={<CopyOutlined />} onClick={() => copyToClipboard(spSignOnUrl, 'Sign-on URL')}>
                  Copy
                </Button>
              </div>
              <Input readOnly value={spSignOnUrl} style={{ background: 'white' }} />
            </div>

            {/* Metadata URL */}
            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                <Text strong>SP Metadata URL</Text>
                <Button size="small" icon={<CopyOutlined />} onClick={() => copyToClipboard(spMetadataUrl, 'Metadata URL')}>
                  Copy
                </Button>
              </div>
              <Input readOnly value={spMetadataUrl} style={{ background: 'white' }} />
            </div>
          </Card>

          <Alert
            message="Save in Azure before proceeding"
            description="Click 'Save' at the top of Azure's Basic SAML Configuration panel."
            type="success"
            showIcon
          />
        </div>
      )}

      {/* STEP 3: Configure IdP Metadata */}
      {currentStep === 3 && (
        <div>
          <Title level={4}>Step 4 — Configure Identity Provider</Title>
          <Paragraph>
            Import your Microsoft Entra ID federation details using one of the three options below:
          </Paragraph>

          <Radio.Group
            value={idpConfigMethod}
            onChange={e => setIdpConfigMethod(e.target.value)}
            style={{ marginBottom: 20 }}
          >
            <Radio.Button value="url"><LinkOutlined /> Method A: Metadata URL (Recommended)</Radio.Button>
            <Radio.Button value="xml"><UploadOutlined /> Method B: Upload XML</Radio.Button>
            <Radio.Button value="manual"><FormOutlined /> Method C: Manual Entry</Radio.Button>
          </Radio.Group>

          {/* Method A: URL */}
          {idpConfigMethod === 'url' && (
            <Card style={{ background: '#f8fafc', border: '1px solid #e2e8f0' }}>
              <Paragraph>
                In Azure under <strong>3. SAML Certificates</strong>, copy the <strong>App Federation Metadata Url</strong> and paste it here:
              </Paragraph>
              <Space.Compact style={{ width: '100%' }}>
                <Input
                  placeholder="https://login.microsoftonline.com/.../federationmetadata.xml"
                  value={metadataUrlInput}
                  onChange={e => setMetadataUrlInput(e.target.value)}
                />
                <Button type="primary" onClick={handleFetchUrl} loading={loading}>
                  Fetch & Verify
                </Button>
              </Space.Compact>
            </Card>
          )}

          {/* Method B: XML */}
          {idpConfigMethod === 'xml' && (
            <Card style={{ background: '#f8fafc', border: '1px solid #e2e8f0' }}>
              <Paragraph>
                Download the <strong>Federation Metadata XML</strong> from Azure and upload it, or paste its contents:
              </Paragraph>
              <Upload.Dragger
                beforeUpload={file => {
                  const reader = new FileReader();
                  reader.onload = e => {
                    const text = e.target?.result as string;
                    setMetadataXmlInput(text);
                    handleParseXml(text);
                  };
                  reader.readAsText(file);
                  return false;
                }}
                showUploadList={false}
                style={{ padding: 16, marginBottom: 16 }}
              >
                <p className="ant-upload-drag-icon">
                  <UploadOutlined style={{ fontSize: 32, color: '#0284c7' }} />
                </p>
                <p className="ant-upload-text">Click or drag Federation Metadata XML file here</p>
              </Upload.Dragger>
              <TextArea
                rows={4}
                placeholder="Or paste XML content here..."
                value={metadataXmlInput}
                onChange={e => setMetadataXmlInput(e.target.value)}
              />
              <Button style={{ marginTop: 12 }} onClick={() => handleParseXml(metadataXmlInput)} loading={loading}>
                Parse XML Content
              </Button>
            </Card>
          )}

          {/* Method C: Manual */}
          {idpConfigMethod === 'manual' && (
            <Form layout="vertical">
              <Form.Item label="IdP Entity ID / Issuer" required>
                <Input
                  value={provider.idp_entity_id}
                  onChange={e => setProvider({ ...provider, idp_entity_id: e.target.value })}
                />
              </Form.Item>
              <Form.Item label="Login URL (Single Sign-On Service)" required>
                <Input
                  value={provider.idp_sso_url}
                  onChange={e => setProvider({ ...provider, idp_sso_url: e.target.value })}
                />
              </Form.Item>
              <Form.Item label="Logout URL (Optional)">
                <Input
                  value={provider.idp_slo_url || ''}
                  onChange={e => setProvider({ ...provider, idp_slo_url: e.target.value })}
                />
              </Form.Item>
              <Form.Item label="X.509 Signing Certificate (Base64)" required>
                <TextArea
                  rows={4}
                  value={provider.idp_x509_cert}
                  onChange={e => setProvider({ ...provider, idp_x509_cert: e.target.value })}
                />
              </Form.Item>
            </Form>
          )}

          {/* Display currently extracted values */}
          {provider.idp_entity_id && (
            <div style={{ marginTop: 20, padding: 16, background: '#f0fdf4', borderRadius: 8, border: '1px solid #bbf7d0' }}>
              <Text strong style={{ color: '#16a34a' }}><CheckCircleOutlined /> Extracted Identity Provider Details:</Text>
              <ul style={{ margin: '8px 0 0', paddingLeft: 20, fontSize: 13 }}>
                <li><strong>Entity ID:</strong> {provider.idp_entity_id}</li>
                <li><strong>SSO Login URL:</strong> {provider.idp_sso_url}</li>
                <li><strong>Certificate:</strong> Configured ({provider.idp_x509_cert ? 'Present' : 'Missing'})</li>
              </ul>
            </div>
          )}
        </div>
      )}

      {/* STEP 4: Validate Configuration */}
      {currentStep === 4 && (
        <div>
          <Title level={4}>Step 5 — Configuration Validation Checklist</Title>
          <Paragraph>
            Review the security checklist below before activating the provider:
          </Paragraph>

          <Button
            type="primary"
            icon={<SaveOutlined />}
            onClick={handleSaveAndValidate}
            loading={loading}
            style={{ marginBottom: 20 }}
          >
            Run Validation Checklist
          </Button>

          {validationResult && (
            <div style={{ background: '#f8fafc', padding: 16, borderRadius: 8, border: '1px solid #e2e8f0' }}>
              <div style={{ marginBottom: 12 }}>
                Overall: <Tag color={validationResult.valid ? 'success' : 'error'} style={{ fontSize: 14, padding: '2px 10px' }}>
                  {validationResult.valid ? 'VALIDATED' : 'ERRORS FOUND'}
                </Tag>
              </div>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                {validationResult.checks.map((check, index) => (
                  <li key={index} style={{ padding: '8px 0', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center' }}>
                    <span style={{ marginRight: 12 }}>
                      {check.passed ? (
                        <CheckCircleOutlined style={{ color: '#16a34a', fontSize: 18 }} />
                      ) : (
                        <Tag color={check.severity === 'error' ? 'error' : 'warning'}>{check.severity.toUpperCase()}</Tag>
                      )}
                    </span>
                    <div>
                      <strong>{check.name}:</strong> <span style={{ color: '#475569' }}>{check.message}</span>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {/* STEP 5: Test SSO Login */}
      {currentStep === 5 && (
        <div>
          <Title level={4}>Step 6 — Test SSO Login Flow</Title>
          <Paragraph>
            Test end-to-end authentication with Microsoft Entra ID. This initiates a real SAML flow in read-only
            preview mode without modifying any production database user accounts:
          </Paragraph>

          <div style={{ textAlign: 'center', padding: '32px 0' }}>
            <Button
              type="primary"
              size="large"
              icon={<ExperimentOutlined />}
              href={`/sso/test/${provider.name}`}
              target="_blank"
              style={{ background: '#0284c7', borderColor: '#0284c7' }}
            >
              Launch End-to-End Test in New Tab
            </Button>
            <div style={{ marginTop: 12, color: '#64748b', fontSize: 13 }}>
              Opens Azure login dialog and displays verified SAML claims and calculated Pimcore roles.
            </div>
          </div>

          <Alert
            message="Ensure users are assigned in Entra"
            description="In the Microsoft Entra admin center, under your enterprise application, ensure that test users or groups are assigned in 'Users and groups'."
            type="info"
            showIcon
          />
        </div>
      )}

      {/* STEP 6: Activate Provider */}
      {currentStep === 6 && (
        <div>
          <Title level={4}>Step 7 — Activate Identity Provider</Title>
          <Paragraph>
            Your Microsoft Entra ID provider is configured and ready to be activated.
          </Paragraph>

          <Card style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', marginBottom: 24 }}>
            <Paragraph>
              <strong>Final Login URL:</strong> <code>{origin}/sso/login/{provider.name}</code>
            </Paragraph>
            <Paragraph>
              <strong>Login Button:</strong> "{provider.login_button_label}" will be visible on the Pimcore Studio login screen.
            </Paragraph>
            <Paragraph style={{ color: '#15803d', margin: 0 }}>
              Local administrative login remains available at all times for recovery.
            </Paragraph>
          </Card>

          <Button
            type="primary"
            size="large"
            icon={<CheckCircleOutlined />}
            onClick={handleActivate}
            loading={loading}
            style={{ background: '#16a34a', borderColor: '#16a34a' }}
          >
            Activate Microsoft Entra ID SSO
          </Button>
        </div>
      )}

      <Divider />

      {/* Navigation Buttons */}
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <Button
          disabled={currentStep === 0}
          onClick={() => setCurrentStep(currentStep - 1)}
          icon={<ArrowLeftOutlined />}
        >
          Previous
        </Button>

        {currentStep < steps.length - 1 && (
          <Button
            type="primary"
            onClick={() => {
              if (currentStep === 4 && !validationResult) {
                handleSaveAndValidate().then(() => setCurrentStep(currentStep + 1));
              } else {
                setCurrentStep(currentStep + 1);
              }
            }}
          >
            Next <ArrowRightOutlined />
          </Button>
        )}
      </div>
    </Card>
  );
};
