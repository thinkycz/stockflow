export type MonthlyShiftSummary = {
    worker_id: number;
    worker_name: string;
    color: string;
    hours: number;
    average_score: number | null;
    evaluated_shifts: number;
    good_shifts: number;
    late_arrivals: number;
    early_departures: number;
    break_issues: number;
    absences: number;
    salary?: number;
};
