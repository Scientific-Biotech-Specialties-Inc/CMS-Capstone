<script setup lang="ts">
import { Building2, MapPin, Star } from 'lucide-vue-next'
import type { VendorSuggestionCandidate } from '@/types/vendorSuggestion'

const props = defineProps<{
  candidate: VendorSuggestionCandidate
  selected:  boolean
}>()

defineEmits<{ toggle: [] }>()
</script>

<template>
  <label
    class="flex items-start gap-3 p-4 rounded-lg border cursor-pointer transition-colors"
    :class="selected ? 'border-brand-blue bg-brand-blue/[0.04]' : 'border-black/8 hover:border-black/15'"
  >
    <input type="checkbox" :checked="selected" @change="$emit('toggle')"
      class="mt-1 w-4 h-4 rounded border-black/20 text-brand-navy focus:ring-brand-blue/30" />

    <div class="flex-1 min-w-0">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-sm font-semibold text-black truncate">{{ candidate.name }}</h4>
        <span v-if="candidate.suggestionScore != null" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 shrink-0">
          <Star class="w-3 h-3 fill-current" /> {{ candidate.suggestionScore }}
        </span>
      </div>

      <div class="flex items-center gap-3 mt-1.5 text-xs text-black/50">
        <span v-if="candidate.industry" class="inline-flex items-center gap-1">
          <Building2 class="w-3 h-3" /> {{ candidate.industry }}
        </span>
        <span v-if="candidate.region" class="inline-flex items-center gap-1">
          <MapPin class="w-3 h-3" /> {{ candidate.region }}
        </span>
      </div>

      <p v-if="candidate.suggestionReason" class="text-xs text-black/45 mt-2 leading-relaxed">
        {{ candidate.suggestionReason }}
      </p>

      <div class="flex flex-wrap gap-3 mt-2 text-[11px] text-black/35">
        <span v-if="candidate.contactEmail">{{ candidate.contactEmail }}</span>
        <span v-if="candidate.contactNumber">{{ candidate.contactNumber }}</span>
      </div>
    </div>
  </label>
</template>
