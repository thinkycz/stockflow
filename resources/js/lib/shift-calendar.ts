type TimedShift = {
    id: number;
    start_time: string;
    end_time: string;
};

export function sortShiftsByTime<T extends TimedShift>(
    shifts: readonly T[],
): T[] {
    return [...shifts].sort(
        (left, right) =>
            left.start_time.localeCompare(right.start_time) ||
            left.end_time.localeCompare(right.end_time) ||
            left.id - right.id,
    );
}
