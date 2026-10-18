import React, { useState, useEffect } from 'react';
import { Card, Table, Tag, Input, Select, Space, Button, Modal, Typography, message, Divider } from 'antd';
import {
  FileTextOutlined,
  ReloadOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  StopOutlined,
  SearchOutlined
} from '@ant-design/icons';
import { AuditLogItem } from '../types';
import { ssoApi } from '../services/api';

const { Text, Paragraph } = Typography;

export const AuditLogTable: React.FC = () => {
  const [logs, setLogs] = useState<AuditLogItem[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(20);
  const [loading, setLoading] = useState(false);

  const [statusFilter, setStatusFilter] = useState<string | undefined>(undefined);
  const [usernameFilter, setUsernameFilter] = useState('');

  const [selectedLog, setSelectedLog] = useState<AuditLogItem | null>(null);

  const fetchLogs = async (currentPage = page, status = statusFilter, username = usernameFilter) => {
    setLoading(true);
    try {
      const res = await ssoApi.getLogs({
        page: currentPage,
        limit,
        status,
        username,
      });
      setLogs(res.items);
      setTotal(res.total);
    } catch (e: any) {
      message.error(`Failed to load audit logs: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs(1);
  }, [statusFilter]);

  const handleSearch = () => {
    setPage(1);
    fetchLogs(1, statusFilter, usernameFilter);
  };

  const columns = [
    {
      title: 'Timestamp',
      dataIndex: 'timestamp',
      key: 'timestamp',
      render: (ts: string) => new Date(ts).toLocaleString(),
      width: 170,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 110,
      render: (st: string) => {
        if (st === 'SUCCESS') return <Tag color="success"><CheckCircleOutlined /> SUCCESS</Tag>;
        if (st === 'DENIED') return <Tag color="error"><StopOutlined /> DENIED</Tag>;
        return <Tag color="warning"><CloseCircleOutlined /> FAILED</Tag>;
      },
    },
    {
      title: 'User',
      key: 'user',
      render: (_: any, record: AuditLogItem) => (
        <div>
          <strong>{record.username || 'Unknown'}</strong>
          {record.external_user_id && (
            <div style={{ color: '#64748b', fontSize: 11 }}>
              ID: {record.external_user_id}
            </div>
          )}
        </div>
      ),
    },
    {
      title: 'Provider',
      dataIndex: 'provider_name',
      key: 'provider_name',
      render: (name: string) => name || 'N/A',
    },
    {
      title: 'Result / Reason',
      dataIndex: 'reason',
      key: 'reason',
      render: (reason: string) => reason || 'N/A',
    },
    {
      title: 'Correlation ID',
      dataIndex: 'correlation_id',
      key: 'correlation_id',
      width: 130,
      render: (cid: string) => <code>{cid}</code>,
    },
    {
      title: 'Details',
      key: 'details',
      width: 90,
      render: (_: any, record: AuditLogItem) => (
        <Button size="small" onClick={() => setSelectedLog(record)}>
          Inspect
        </Button>
      ),
    },
  ];

  return (
    <Card
      title={<Space><FileTextOutlined /><span>SSO Authentication Events & Diagnostics Log</span></Space>}
      extra={<Button icon={<ReloadOutlined />} onClick={() => fetchLogs()}>Refresh</Button>}
      style={{ borderRadius: 8, boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}
    >
      <Paragraph style={{ color: '#64748b' }}>
        Live monitoring of Single Sign-On authentication attempts, role resolutions, and security events.
      </Paragraph>

      {/* Filters */}
      <div style={{ display: 'flex', gap: 12, marginBottom: 16 }}>
        <Select
          allowClear
          placeholder="Filter by Status"
          style={{ width: 160 }}
          value={statusFilter}
          onChange={val => setStatusFilter(val)}
          options={[
            { label: 'All Statuses', value: undefined },
            { label: 'SUCCESS', value: 'SUCCESS' },
            { label: 'DENIED', value: 'DENIED' },
            { label: 'FAILED', value: 'FAILED' },
          ]}
        />
        <Input
          placeholder="Search by Username"
          style={{ width: 220 }}
          value={usernameFilter}
          onChange={e => setUsernameFilter(e.target.value)}
          onPressEnter={handleSearch}
        />
        <Button icon={<SearchOutlined />} onClick={handleSearch}>Search</Button>
      </div>

      <Table
        dataSource={logs}
        columns={columns}
        rowKey="id"
        loading={loading}
        pagination={{
          current: page,
          pageSize: limit,
          total,
          onChange: p => {
            setPage(p);
            fetchLogs(p);
          },
        }}
      />

      {/* Event Details Modal */}
      <Modal
        title={`Authentication Event Details [${selectedLog?.correlation_id}]`}
        open={selectedLog !== null}
        onCancel={() => setSelectedLog(null)}
        footer={<Button onClick={() => setSelectedLog(null)}>Close</Button>}
        width={680}
      >
        {selectedLog && (
          <div>
            <p><strong>Timestamp:</strong> {new Date(selectedLog.timestamp).toLocaleString()}</p>
            <p><strong>Status:</strong> {selectedLog.status}</p>
            <p><strong>User:</strong> {selectedLog.username || 'N/A'} ({selectedLog.external_user_id || 'No external ID'})</p>
            <p><strong>IP Address:</strong> {selectedLog.ip_address || 'Unknown'}</p>
            <p><strong>User Agent:</strong> {selectedLog.user_agent || 'Unknown'}</p>
            <p><strong>Assigned Role IDs:</strong> {selectedLog.assigned_roles?.join(', ') || 'None'}</p>
            <p><strong>Explanation:</strong> {selectedLog.reason || 'None'}</p>
            <Divider />
            <Text strong>Incoming Claims Summary (Redacted for Security):</Text>
            <pre style={{ background: '#0f172a', color: '#e2e8f0', padding: 12, borderRadius: 6, maxHeight: 200, overflowY: 'auto' }}>
              {JSON.stringify(selectedLog.attributes_summary, null, 2)}
            </pre>
          </div>
        )}
      </Modal>
    </Card>
  );
};
