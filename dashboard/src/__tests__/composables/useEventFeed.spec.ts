import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest'
import {flushPromises, mount} from '@vue/test-utils'
import {defineComponent} from 'vue'
import {useEventFeed} from '@/composables/useEventFeed'
import {api} from '@/services/api'

vi.mock('@/services/api', () => ({
    api: {fetchEventFeed: vi.fn()},
}))

function makeEvent(id: string) {
    return {
        id,
        event_type: 'order.created',
        order_id: 'ord-1',
        routing_key: 'order.created',
        published_at: new Date().toISOString(),
        payload: {},
        attempt: 1,
        processed: false
    }
}

describe('useEventFeed', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })
    afterEach(() => {
        vi.useRealTimers()
    })

    it('new events are prepended to the top of the list', async () => {
        vi.mocked(api.fetchEventFeed)
            .mockResolvedValueOnce([makeEvent('evt-1')] as any)
            .mockResolvedValueOnce([makeEvent('evt-2'), makeEvent('evt-1')] as any)

        let captured: any
        mount(defineComponent({
            setup() {
                const {events} = useEventFeed(1000)
                captured = events
                return {}
            },
            template: '<div></div>',
        }))

        await flushPromises()
        expect(captured.value[0].id).toBe('evt-1')

        vi.advanceTimersByTime(1000)
        await flushPromises()

        expect(captured.value[0].id).toBe('evt-2')
        expect(captured.value[1].id).toBe('evt-1')
    })

    it('caps the list at 100 events', async () => {
        const batch = Array.from({length: 60}, (_, i) => makeEvent(`e-${i}`))
        vi.mocked(api.fetchEventFeed)
            .mockResolvedValueOnce(batch.slice(0, 50) as any)
            .mockResolvedValueOnce(batch as any)

        let captured: any
        mount(defineComponent({
            setup() {
                const {events} = useEventFeed(1000)
                captured = events
                return {}
            },
            template: '<div></div>',
        }))

        await flushPromises()

        vi.advanceTimersByTime(1000)
        await flushPromises()

        expect(captured.value.length).toBeLessThanOrEqual(100)
    })
})
