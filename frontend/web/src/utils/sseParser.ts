export interface SseFrame {
  event: string
  data: string
  id?: string
}

/**
 * Incrementally parses Server-Sent Events (SSE) text chunks across ReadableStream reads.
 * Returns parsed complete frames and any trailing incomplete buffer to carry over.
 */
export function parseSseChunk(
  buffer: string,
  chunk: string
): { frames: SseFrame[]; remainder: string } {
  const combined = (buffer + chunk).replace(/\r\n/g, '\n')
  const rawBlocks = combined.split('\n\n')
  const remainder = rawBlocks.pop() ?? ''
  const frames: SseFrame[] = []

  for (const block of rawBlocks) {
    if (!block.trim()) continue

    let event = 'message'
    let id: string | undefined
    const dataLines: string[] = []

    for (const line of block.split('\n')) {
      // Ignore SSE comments / heartbeats (lines starting with ':')
      if (!line || line.startsWith(':')) continue

      const colonIdx = line.indexOf(':')
      const field = colonIdx === -1 ? line : line.slice(0, colonIdx)
      const rawValue = colonIdx === -1 ? '' : line.slice(colonIdx + 1)
      const value = rawValue.startsWith(' ') ? rawValue.slice(1) : rawValue

      if (field === 'event') {
        event = value || 'message'
      } else if (field === 'data') {
        dataLines.push(value)
      } else if (field === 'id') {
        id = value
      }
    }

    if (dataLines.length > 0) {
      frames.push({
        event,
        data: dataLines.join('\n'),
        ...(id !== undefined ? { id } : {}),
      })
    }
  }

  return { frames, remainder }
}
