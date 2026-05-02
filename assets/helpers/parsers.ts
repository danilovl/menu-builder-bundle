export function parseJson<T>(str: string, fallback: T): [T, string | null] {
  if (!str || !str.trim()) {
    return [fallback, null]
  }

  try {
    return [JSON.parse(str) as T, null]
  } catch (e) {
    return [fallback, (e as Error).message]
  }
}

export function csvToList(s: string): string[] {
  return (s || '')
    .split(',')
    .map((x) => {
      return x.trim()
    })
    .filter(Boolean)
}
