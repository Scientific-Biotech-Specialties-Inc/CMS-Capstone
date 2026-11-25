<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Package, Search, X } from 'lucide-vue-next'
import { onClickOutside } from '@vueuse/core'
import { usePrsContractHandoff, type CatalogProduct } from '@/composables/usePrsContractHandoff'

const props = defineProps<{
  selectedProductId?: number
}>()

const emit = defineEmits<{
  select: [product: CatalogProduct]
  clear: []
}>()

const { fetchCatalogProducts } = usePrsContractHandoff()
const products = ref<CatalogProduct[]>([])
const loading = ref(false)
const open = ref(false)
const search = ref('')
const containerRef = ref<HTMLElement | null>(null)

onMounted(async () => {
  loading.value = true
  try {
    products.value = await fetchCatalogProducts()
  } finally {
    loading.value = false
  }
})

const selectedProduct = computed(() =>
  products.value.find(p => p.productId === props.selectedProductId) ?? null
)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return products.value.slice(0, 25)
  return products.value.filter(p =>
    p.productName.toLowerCase().includes(q)
    || p.productCode.toLowerCase().includes(q)
    || (p.serialNumber ?? '').toLowerCase().includes(q)
    || (p.brand ?? '').toLowerCase().includes(q)
    || (p.model ?? '').toLowerCase().includes(q)
  ).slice(0, 25)
})

function choose(p: CatalogProduct) {
  open.value = false
  search.value = ''
  emit('select', p)
}

onClickOutside(containerRef, () => {
  open.value = false
})
</script>

<template>
  <div ref="containerRef" class="relative mb-4">
    <label class="block text-xs font-semibold text-black/55 mb-1.5">
      Synced PRS Product Catalog <span class="font-normal text-black/40">(Optional — auto-fills item details)</span>
    </label>
    <div class="flex items-center gap-2">
      <div class="relative flex-1">
        <Search class="w-3.5 h-3.5 text-black/35 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          v-model="search"
          @focus="open = true"
          type="text"
          :placeholder="selectedProduct
            ? `${selectedProduct.productName} (${selectedProduct.productCode})`
            : (loading ? 'Loading synced products...' : 'Search by product name, code, serial, brand, or model...')"
          class="w-full h-9 rounded-lg border border-black/12 bg-white pl-8.5 pr-3 text-sm placeholder:text-black/40 focus:outline-none focus:ring-2 focus:border-brand-blue focus:ring-brand-blue/15 transition"
        />
      </div>
      <button
        v-if="selectedProductId"
        type="button"
        @click="emit('clear')"
        class="h-9 px-3 rounded-lg border border-black/12 bg-white text-xs font-medium text-black/60 hover:text-black hover:bg-black/4 inline-flex items-center gap-1"
      >
        <X class="w-3.5 h-3.5" /> Clear
      </button>
    </div>

    <div
      v-if="open && !loading"
      class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 max-h-60 overflow-y-auto bg-white border border-black/10 rounded-lg shadow-lg py-1 divide-y divide-black/[0.04]"
    >
      <div v-if="filtered.length === 0" class="px-3.5 py-3 text-xs text-black/45">
        No synced products match your search. You can still enter item details manually below.
      </div>
      <button
        v-for="p in filtered"
        :key="p.productId"
        type="button"
        @click="choose(p)"
        class="w-full text-left px-3.5 py-2.5 hover:bg-[#EFF6FF] transition-colors flex items-center justify-between gap-3"
      >
        <div class="min-w-0 flex items-center gap-2.5">
          <Package class="w-4 h-4 text-[#252578] shrink-0" />
          <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-800 truncate">{{ p.productName }}</p>
            <p class="text-[11px] text-slate-500 truncate">
              {{ p.productCode }}
              <span v-if="p.serialNumber"> · SN: {{ p.serialNumber }}</span>
              <span v-if="p.brand || p.model"> · {{ [p.brand, p.model].filter(Boolean).join(' ') }}</span>
            </p>
          </div>
        </div>
        <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700 shrink-0">
          {{ p.sbuNumber }}
        </span>
      </button>
    </div>
  </div>
</template>
