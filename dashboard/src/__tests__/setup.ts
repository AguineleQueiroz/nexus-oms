import {vi} from 'vitest'

// CountUp.js uses requestAnimationFrame which doesn't work in jsdom.
// This mock makes CountUp synchronous so stats values appear immediately in tests.
vi.mock('countup.js', () => ({
    CountUp: class {
        private el: HTMLElement | null = null
        private endVal: number

        constructor(target: HTMLElement | string, endVal: number) {
            this.endVal = endVal
            if (typeof target === 'string') {
                this.el = document.getElementById(target)
            } else {
                this.el = target
            }
        }

        start(cb?: () => void) {
            if (this.el) this.el.textContent = String(this.endVal)
            cb?.()
        }

        update(newVal: number) {
            this.endVal = newVal
            if (this.el) this.el.textContent = String(newVal)
        }
    },
}))
