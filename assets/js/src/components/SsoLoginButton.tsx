import React, { useEffect, useState } from 'react';
import { Button, Divider, Space } from 'antd';
import { WindowsOutlined, SafetyOutlined } from '@ant-design/icons';
import { PublicConfigResponse, PublicProviderConfig } from '../types';

interface SsoLoginButtonProps {
  hideCredentialsForm?: boolean;
  onHideCredentialsForm?: (hide: boolean) => void;
}

export const SsoLoginButton: React.FC<SsoLoginButtonProps> = ({
  hideCredentialsForm = false,
  onHideCredentialsForm,
}) => {
  const [config, setConfig] = useState<PublicConfigResponse | null>(null);

  useEffect(() => {
    fetch('/api/sso/public-config')
      .then(res => res.json())
      .then((data: PublicConfigResponse) => {
        setConfig(data);

        // Handle auto-redirect if configured
        if (data.auto_redirect_url && !window.location.search.includes('local=1')) {
          window.location.href = data.auto_redirect_url;
          return;
        }

        // Handle hiding local credentials form if requested
        if (data.hide_local_login && onHideCredentialsForm && !window.location.search.includes('local=1')) {
          onHideCredentialsForm(true);
        }
      })
      .catch(() => {
        // Silently ignore if public config endpoint is unavailable
      });
  }, []);

  if (!config || !config.sso_available || config.providers.length === 0) {
    return null;
  }

  return (
    <div style={{ marginTop: 16, width: '100%' }}>
      {!hideCredentialsForm && (
        <Divider plain style={{ margin: '16px 0', color: '#94a3b8', fontSize: 13 }}>
          Or sign in with
        </Divider>
      )}

      <Space direction="vertical" style={{ width: '100%' }} size={10}>
        {config.providers.map((p: PublicProviderConfig) => (
          <Button
            key={p.slug}
            type={hideCredentialsForm ? 'primary' : 'default'}
            size="large"
            block
            icon={
              p.icon === 'microsoft' ? (
                <WindowsOutlined style={{ color: hideCredentialsForm ? '#fff' : '#0078d4' }} />
              ) : (
                <SafetyOutlined />
              )
            }
            href={p.login_url}
            style={{
              height: 44,
              fontWeight: 500,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            {p.label}
          </Button>
        ))}

        {hideCredentialsForm && (
          <div style={{ textAlign: 'center', marginTop: 16 }}>
            <Button
              type="link"
              size="small"
              onClick={() => onHideCredentialsForm && onHideCredentialsForm(false)}
              style={{ color: '#64748b', fontSize: 12 }}
            >
              Emergency / Local Administrator Login
            </Button>
          </div>
        )}
      </Space>
    </div>
  );
};
