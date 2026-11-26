import { describe, expect, it } from 'vitest'
import { parseSseChunk } from '../sseParser'

describe('parseSseChunk', () => {
  it('parses complete SSE frames with event, id, and JSON data', () => {
    const chunk = 'id: 2026-11-27T00:00:00Z\nevent: notification\ndata: {"id":"10","message":"New contract"}\n\n'
    const { frames, remainder } = parseSseChunk('', chunk)

    expect(remainder).toBe('')
    expect(frames).toHaveLength(1)
    expect(frames[0]).toEqual({
      id: '2026-11-27T00:00:00Z',
      event: 'notification',
      data: '{"id":"10","message":"New contract"}',
    })
  })

  it('buffers partial chunks across reads and parses once delimiter arrives', () => {
    const first = parseSseChunk('', 'event: notification\ndata: {"id":"42"')
    expect(first.frames).toHaveLength(0)
    expect(first.remainder).toContain('event: notification')

    const second = parseSseChunk(first.remainder, ',"message":"Approved"}\n\n')
    expect(second.frames).toHaveLength(1)
    expect(second.frames[0]?.data).toBe('{"id":"42","message":"Approved"}')
    expect(second.remainder).toBe('')
  })

  it('ignores heartbeat comment lines and handles CRLF line endings', () => {
    const chunk = ': heartbeat\r\n\r\nevent: connected\r\ndata: {"status":"ok"}\r\n\r\n'
    const { frames } = parseSseChunk('', chunk)

    expect(frames).toHaveLength(1)
    expect(frames[0]).toEqual({
      event: 'connected',
      data: '{"status":"ok"}',
    })
  })
})
