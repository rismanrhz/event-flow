import { onMounted, onUnmounted, ref, watch } from 'vue'
import type { Ref } from 'vue'

export function useCountUp(target: () => number, duration = 650): Ref<number> {
  const displayed = ref(0)
  const origin = ref(0)
  let raf = 0

  function animate(to: number, from: number) {
    cancelAnimationFrame(raf)
    const start = performance.now()
    const diff = to - from

    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / duration)
      const eased = 1 - Math.pow(1 - progress, 3)
      displayed.value = Math.round(from + diff * eased)
      if (progress < 1) {
        raf = requestAnimationFrame(tick)
      } else {
        displayed.value = to
        origin.value = to
      }
    }

    raf = requestAnimationFrame(tick)
  }

  watch(
    () => target(),
    (to, from) => {
      animate(to, from ?? origin.value)
    },
    { immediate: true },
  )

  onMounted(() => {
    displayed.value = target()
    origin.value = target()
  })

  onUnmounted(() => cancelAnimationFrame(raf))

  return displayed
}