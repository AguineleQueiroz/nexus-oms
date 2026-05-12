import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ThroughputChart from '@/components/charts/ThroughputChart.vue'
import type { ThroughputPoint } from '@/types'

const data: ThroughputPoint[] = [
  { minute: '2024-01-01T10:00:00Z', count: 5 },
  { minute: '2024-01-01T10:01:00Z', count: 8 },
  { minute: '2024-01-01T10:02:00Z', count: 3 },
]

describe('ThroughputChart', () => {
  it('renders an SVG element when data is provided', () => {
    const wrapper = mount(ThroughputChart, { props: { data } })
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('shows placeholder text when data is empty', () => {
    const wrapper = mount(ThroughputChart, { props: { data: [] } })
    expect(wrapper.find('[data-testid="chart-empty"]').exists()).toBe(true)
  })

  it('SVG is not rendered when data is empty', () => {
    const wrapper = mount(ThroughputChart, { props: { data: [] } })
    expect(wrapper.find('svg').exists()).toBe(false)
  })
})
