export function storeSwitchRefreshUrl(currentUrl: string): string {
    const url = new URL(currentUrl);

    url.searchParams.delete('store_id');

    return `${url.pathname}${url.search}${url.hash}`;
}
