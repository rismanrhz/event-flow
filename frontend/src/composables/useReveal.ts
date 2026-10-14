import { onBeforeUnmount, onMounted, watch } from 'vue'
import type { Ref } from 'vue'

/**
 * Reveals elements matching `selector` inside `container` by adding the
 * `is-revealed` class once they enter (or have scrolled past) the viewport.
 * Used together with the `.reveal` transition classes defined in
 * `src/style.css` for staggered scroll-in animations.
 *
 * A scroll/resize listener is used instead of IntersectionObserver so that
 * elements are never skipped during fast scrolls or jumps. Listeners are
 * registered in capture mode so inner scroll containers are covered too.
 * Reduced-motion is handled by the CSS media query.
 */
export function useReveal(container: Ref<HTMLElement | null>, selector = '.reveal'): { refresh: () => void } {
  let frame = 0
  let elements: HTMLElement[] = []

  function revealVisible(): void {
    frame = 0
    if (!elements.length) return

    const viewport = window.innerHeight || document.documentElement.clientHeight
    const limit = viewport - 48

    for (const el of elements) {
      if (el.classList.contains('is-revealed')) continue
      if (el.getBoundingClientRect().top < limit) {
        el.classList.add('is-revealed')
      }
    }
  }

  function schedule(): void {
    if (frame) return
    frame = requestAnimationFrame(revealVisible)
  }

  function update(): void {
    elements = container.value ? Array.from(container.value.querySelectorAll<HTMLElement>(selector)) : []
    revealVisible()
  }

  onMounted(() => {
    update()
    window.addEventListener('scroll', schedule, { passive: true, capture: true })
    window.addEventListener('resize', schedule, { passive: true })
  })

  watch(container, update)

  onBeforeUnmount(() => {
    if (frame) cancelAnimationFrame(frame)
    window.removeEventListener('scroll', schedule, true)
    window.removeEventListener('resize', schedule)
  })

  return { refresh: update }
}
