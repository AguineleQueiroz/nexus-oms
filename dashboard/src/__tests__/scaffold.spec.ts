import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

describe('Vite scaffold', () => {
  it('vite.config.ts exists and references vue plugin', () => {
    const content = readFileSync(resolve(__dirname, '../../vite.config.ts'), 'utf-8')
    expect(content).toContain('@vitejs/plugin-vue')
  })

  it('tailwind.config.ts exists and has content path for src', () => {
    const content = readFileSync(resolve(__dirname, '../../tailwind.config.ts'), 'utf-8')
    expect(content).toContain('src/**')
  })
})
