export type Worker = {
    id: number;
    first_name: string;
    last_name: string;
    color: string;
    attendance_rating_enabled: boolean;
    archived: boolean;
};

export type Shift = {
    id: number;
    worker_id: number;
    date: string;
    start_time: string;
    end_time: string;
    attendance_rating?: AttendanceRating;
};

export type AttendanceRatingReason =
    | 'late_arrival'
    | 'early_departure'
    | 'excessive_break_duration'
    | 'excessive_break_count'
    | 'absence';

export type AttendanceRating = {
    state: 'future' | 'pending' | 'scored' | 'disabled';
    score: number | null;
    band: 'good' | 'warning' | 'poor' | null;
    reason_codes: AttendanceRatingReason[];
    arrival_offset_minutes: number | null;
    departure_offset_minutes: number | null;
    break_minutes: number | null;
    break_count: number | null;
};

export type CalendarShift = Shift & {
    worker_name: string;
    worker_color: string;
};

export type ShiftRequest = {
    id: number;
    worker_id: number;
    date: string;
    start_time: string;
    end_time: string;
};

export type CalendarRequest = ShiftRequest & {
    worker_name: string;
    worker_color: string;
};

export type ShiftPreset = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
};

export type ShiftShareLink = {
    id: number;
    name: string;
    url: string;
    created_at: string;
};

export type CalendarDay = {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    shifts: CalendarShift[];
    requests: CalendarRequest[];
};
