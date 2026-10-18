import React from 'react';
import { OverviewMetrics } from '../types';
interface OverviewDashboardProps {
    metrics: OverviewMetrics | null;
    loading: boolean;
    onAddProvider: () => void;
    onTestConfig: () => void;
    onViewLogs: () => void;
    onOpenGuide: () => void;
}
export declare const OverviewDashboard: React.FC<OverviewDashboardProps>;
export {};
