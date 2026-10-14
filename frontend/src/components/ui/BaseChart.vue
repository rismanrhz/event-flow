<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { EChartsOption } from 'echarts'
import echarts, { type ChartInstance } from '@/composables/echarts'

const props = withDefaults(
  defineProps<{
    option: EChartsOption
    height?: string
  }>(),
  { height: '260px' },
)

const root = ref<HTMLDivElement | null>(null)
let chart: ChartInstance | null = null
let observer: ResizeObserver | null = null

function render() {
  if (chart) {
    chart.setOption(props.option, { notMerge: true, lazyUpdate: true })
  }
}

onMounted(() => {
  if (root.value) {
    chart = echarts.init(root.value)
    render()
    observer = new ResizeObserver(() => chart?.resize())
    observer.observe(root.value)
  }
})

watch(() => props.option, render, { deep: true })

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
  chart?.dispose()
  chart = null
})
</script>

<template>
  <div ref="root" role="img" :aria-label="'Chart'" :style="{ height, width: '100%' }" />
</template>