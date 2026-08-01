export type PayrollAdjustment = {
    id: number;
    type: 'tip' | 'deduction';
    amount: number;
    reason: string;
};

export type PayrollShift = {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    planned_minutes: number;
    hourly_rate: number;
    amount: number;
    actual_seconds: number;
    difference_seconds: number | null;
    attendance_incomplete: boolean;
};

export type PayrollAttendance = {
    id: number;
    worker_id: number;
    shift_id: number | null;
    date: string;
    started_at: string;
    ended_at: string | null;
    break_seconds: number;
    actual_seconds: number | null;
    voided: boolean;
    stale: boolean;
};

export type Payslip = {
    worker_id: number;
    worker_name: string;
    planned_minutes: number;
    actual_seconds: number;
    automatic_base_amount: number;
    payable_hours: number;
    payable_hourly_rate: number;
    wage_overridden: boolean;
    base_amount: number;
    tip_amount: number;
    deduction_amount: number;
    final_amount: number;
    incomplete_count: number;
    unmatched_count: number;
    shifts: PayrollShift[];
    attendance: PayrollAttendance[];
    adjustments: PayrollAdjustment[];
};

export type PayrollReport = {
    id: number | null;
    year: number;
    month: number;
    status: 'open' | 'closed';
    closed_at: string | null;
    reopened_at: string | null;
    payslips: Payslip[];
};
