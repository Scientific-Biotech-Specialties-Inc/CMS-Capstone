export function safeHref(url: string | undefined | null): string {
  if (!url) return ''
  if (/^javascript:/i.test(url.trim())) return ''
  return url
}
