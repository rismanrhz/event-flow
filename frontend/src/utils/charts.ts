import type { EChartsOption } from 'echarts'
import type {
  AttendanceSummary,
  FunnelSummary,
  TaskSummary,
  TrendSeries,
} from '@/types'

export const CHART_COLORS = {
  indigo: '#4f46e5',
  teal: '#0d9488',
  amber: '#f59e0b',
  emerald: '#10b981',
  red: '#ef4444',
  sky: '#0ea5e9',
  violet: '#8b5cf6',
  slate: '#94a3b8',
} as const

const axisLine = {
  axisLine: { lineStyle: { color: '#e2e8f0' } },
  axisTick: { show: false },
  axisLabel: { color: '#64748b', fontSize: 11 },
}

const tooltip = { trigger: 'axis' as const, backgroundColor: '#ffffff', borderColor: '#e2e8f0', textStyle: { color: '#0f172a', fontSize: 12 } }

export function trendOption(series: TrendSeries): EChartsOption {
  return {
    animationDuration: 600,
    tooltip,
    grid: { left: 4, right: 10, top: 30, bottom: 0, containLabel: true },
    legend: {
      right: 0,
      top: 0,
      icon: 'roundRect',
      itemWidth: 10,
      itemHeight: 10,
      textStyle: { color: '#64748b', fontSize: 11 },
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      axisLabel: { color: '#94a3b8', fontSize: 10, hideOverlap: true },
      axisLine: { lineStyle: { color: '#e2e8f0' } },
      axisTick: { show: false },
      data: series.labels,
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      ...axisLine,
      splitLine: { lineStyle: { color: '#f1f5f9' } },
    },
    series: [
      {
        name: 'Registrations',
        type: 'line',
        smooth: true,
        showSymbol: false,
        lineStyle: { width: 2.5, color: CHART_COLORS.indigo },
        itemStyle: { color: CHART_COLORS.indigo },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(79, 70, 229, 0.18)' },
              { offset: 1, color: 'rgba(79, 70, 229, 0)' },
            ],
          },
        },
        data: series.registered,
      },
      {
        name: 'Checked in',
        type: 'line',
        smooth: true,
        showSymbol: false,
        lineStyle: { width: 2.5, color: CHART_COLORS.teal },
        itemStyle: { color: CHART_COLORS.teal },
        data: series.checked_in,
      },
      {
        name: 'Cumulative',
        type: 'line',
        smooth: true,
        showSymbol: false,
        lineStyle: { width: 1.5, type: 'dashed', color: '#cbd5e1' },
        itemStyle: { color: '#cbd5e1' },
        data: series.cumulative,
      },
    ],
  }
}

export function funnelOption(funnel: FunnelSummary): EChartsOption {
  const { registered, checked_in, conversion } = funnel
  const noShow = Math.max(0, registered - checked_in)

  return {
    animationDuration: 600,
    tooltip,
    grid: { left: 4, right: 24, top: 8, bottom: 4, containLabel: true },
    xAxis: { type: 'value', show: false, max: Math.max(1, registered) },
    yAxis: {
      type: 'category',
      data: ['Registered', 'Checked in', 'No-show'],
      ...axisLine,
      axisLabel: { color: '#475569', fontSize: 12, fontWeight: 500 },
    },
    series: [
      {
        type: 'bar',
        barWidth: 18,
        data: [
          { value: registered, itemStyle: { color: CHART_COLORS.indigo } },
          { value: checked_in, itemStyle: { color: CHART_COLORS.teal } },
          { value: noShow, itemStyle: { color: CHART_COLORS.amber } },
        ],
        label: {
          show: true,
          position: 'right',
          color: '#64748b',
          fontSize: 11,
          formatter: (p: unknown) => {
            const point = p as { value: number; dataIndex: number }
            return `${point.value}${point.dataIndex === 1 && conversion > 0 ? ` · ${conversion}% →` : ''}`
          },
        },
      },
    ],
  }
}

export function attendanceHoursOption(attendance: AttendanceSummary): EChartsOption {
  const peakIndex = attendance.hours.values.reduce(
    (max, v, i) => (v > attendance.hours.values[max] ? i : max),
    0,
  )

  return {
    animationDuration: 600,
    tooltip,
    grid: { left: 4, right: 8, top: 8, bottom: 0, containLabel: true },
    xAxis: { type: 'category', ...axisLine, data: attendance.hours.labels, axisLabel: { color: '#94a3b8', fontSize: 9, hideOverlap: true } },
    yAxis: { type: 'value', minInterval: 1, ...axisLine, splitLine: { lineStyle: { color: '#f1f5f9' } } },
    series: [
      {
        type: 'bar',
        barWidth: '56%',
        itemStyle: { borderRadius: [4, 4, 0, 0] },
        data: attendance.hours.values.map((value, index) => ({
          value,
          itemStyle: {
            color: index === peakIndex ? CHART_COLORS.indigo : 'rgba(79, 70, 229, 0.28)',
          },
        })),
      },
    ],
  }
}

export function statusDonutOption(title: string, items: { label: string; tone: string; count: number }[]): EChartsOption {
  const colorMap: Record<string, string> = {
    neutral: CHART_COLORS.slate,
    warning: CHART_COLORS.amber,
    danger: CHART_COLORS.red,
    success: CHART_COLORS.emerald,
    primary: CHART_COLORS.indigo,
  }

  return {
    animationDuration: 600,
    color: items.map((item) => colorMap[item.tone] ?? CHART_COLORS.indigo),
    tooltip: { ...tooltip, trigger: 'item' },
    legend: { bottom: 0, icon: 'circle', itemWidth: 8, itemHeight: 8, textStyle: { color: '#64748b', fontSize: 11 } },
    series: [
      {
        name: title,
        type: 'pie',
        radius: ['52%', '74%'],
        center: ['50%', '44%'],
        padAngle: 3,
        itemStyle: { borderRadius: 6 },
        label: { show: false },
        emphasis: { label: { show: true, fontSize: 13, fontWeight: 600, color: '#0f172a' } },
        data: items.map((item) => ({ name: item.label, value: item.count })),
      },
    ],
  }
}

export function barListOption(
  items: { label: string; value: number }[],
): EChartsOption {
  return {
    animationDuration: 600,
    tooltip,
    grid: { left: 4, right: 16, top: 4, bottom: 0, containLabel: true },
    xAxis: { type: 'value', show: false, max: Math.max(1, ...items.map((i) => i.value)) },
    yAxis: { type: 'category', data: items.map((i) => i.label), ...axisLine, axisLabel: { color: '#475569', fontSize: 12 } },
    series: [
      {
        type: 'bar',
        barWidth: 16,
        itemStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 1,
            y2: 0,
            colorStops: [
              { offset: 0, color: CHART_COLORS.teal },
              { offset: 1, color: CHART_COLORS.indigo },
            ],
          },
          borderRadius: [0, 6, 6, 0],
        },
        label: { show: true, position: 'right', color: '#64748b', fontSize: 11, formatter: '{c}' },
        data: items.map((i) => i.value),
      },
    ],
  }
}

export function taskPriorityOption(tasks: TaskSummary): EChartsOption {
  const data = tasks.by_priority.map((p) => ({
    name: p.label,
    value: p.completion,
    count: p.count,
    done: p.done,
  }))

  return {
    animationDuration: 600,
    tooltip: {
      ...tooltip,
      trigger: 'item',
      formatter: (p: unknown) => {
        const item = p as { data: { name: string; value: number; count: number; done: number } }
        return `${item.data.name}<br/>${item.data.done}/${item.data.count} done · ${item.data.value}%`
      },
    },
    grid: { left: 4, right: 20, top: 8, bottom: 4, containLabel: true },
    xAxis: { type: 'value', max: 100, show: false },
    yAxis: { type: 'category', data: data.map((d) => d.name), ...axisLine, axisLabel: { color: '#475569', fontSize: 12, fontWeight: 500 } },
    series: [
      {
        type: 'bar',
        barWidth: 16,
        itemStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 1,
            y2: 0,
            colorStops: [
              { offset: 0, color: CHART_COLORS.indigo },
              { offset: 1, color: CHART_COLORS.sky },
            ],
          },
          borderRadius: [0, 6, 6, 0],
        },
        label: { show: true, position: 'right', color: '#64748b', fontSize: 11, formatter: '{c}%' },
        data,
      },
    ],
  }
}