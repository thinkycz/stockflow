export type MonthlyShiftSummary = {
    worker_id: number;
    worker_name: string;
    color: string;
    hours: number;
    attendance_rating_enabled: boolean;
    average_score: number | null;
    evaluated_shifts: number | null;
    good_shifts: number | null;
    late_arrivals: number | null;
    early_departures: number | null;
    break_issues: number | null;
    absences: number | null;
    salary?: number;
};
