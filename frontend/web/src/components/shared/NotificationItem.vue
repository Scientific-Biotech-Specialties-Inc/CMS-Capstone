<script setup lang="ts">
import { Star, Trash2, FileText, UserPlus, Handshake, ShieldCheck, AlertCircle, Check, ArchiveRestore } from 'lucide-vue-next'
import { typeColor } from '@/types/notification'
import type { Notification, NotifType } from '@/types/notification'
import type { Component } from 'vue'

defineProps<{ notif: Notification; selected?: boolean; isArchived?: boolean }>()
defineEmits<{ 
  'toggle-read': [id: string]; 
  'toggle-favorite': [id: string]; 
  'delete': [id: string];
  'restore': [id: string];
  'update:selected': [val: boolean];
}>()

const typeIcon: Record<NotifType, Component> = {
  contract: FileText,
  user:     UserPlus,
  partner:  Handshake,
  system:   ShieldCheck,
  reminder: AlertCircle,
}
</script>

<template>
  <div class="flex items-center gap-4 px-6 py-4 border-l-2 hover:bg-black/1.2 transition-colors group"
    :class="!notif.isRead ? 'bg-brand-blue/[0.06] border-l-brand-blue' : 'border-l-transparent'">

    <div class="w-2 shrink-0 flex justify-center">
      <div class="w-2 h-2 rounded-full transition-colors"
        :class="!notif.isRead ? 'bg-brand-blue' : 'bg-transparent'" />
    </div>

    <button type="button" @click="$emit('update:selected', !selected)" 
      class="w-4 h-4 rounded-[4px] flex items-center justify-center border transition-all duration-200 shrink-0 mt-0.5"
      :class="selected ? 'bg-brand-blue border-brand-blue text-white scale-105 shadow-sm' : 'border-black/20 bg-white hover:border-black/40 text-transparent'">
      <Check class="w-3 h-3 transition-opacity" stroke-width="3.5" :class="selected ? 'opacity-100' : 'opacity-0'" />
    </button>

    <button @click="$emit('toggle-favorite', notif.id)" class="shrink-0 transition-colors"
      :class="notif.isFavorite ? 'text-amber-400' : 'text-black/20 hover:text-black/40'">
      <Star class="w-4 h-4" :class="notif.isFavorite ? 'fill-amber-400' : ''" />
    </button>

    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
      :class="typeColor[notif.type]">
      <component :is="typeIcon[notif.type]" class="w-4 h-4" />
    </div>

    <RouterLink v-if="notif.notifType?.startsWith('execution_confirmation_') && notif.contractId"
      :to="{ name: `${$route.path.split('/')[1]}-contracts-detail`, params: { id: notif.contractId }, query: { execution: '1' } }"
      class="flex-1 min-w-0 text-sm leading-snug text-brand-navy hover:underline"
      @click="$emit('toggle-read', notif.id)">
      {{ notif.message }}
    </RouterLink>
    <p v-else class="flex-1 min-w-0 text-sm leading-snug cursor-pointer select-none line-clamp-2 break-words"
      :class="!notif.isRead ? 'text-black font-medium' : 'text-black/55'"
      :title="notif.message"
      @click="$emit('toggle-read', notif.id)">
      {{ notif.message }}
    </p>

    <span class="text-xs text-black/35 shrink-0 whitespace-nowrap">{{ notif.timestamp }}</span>

    <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
      <button v-if="isArchived" @click="$emit('restore', notif.id)"
        title="Restore to inbox"
        class="w-7 h-7 flex items-center justify-center rounded-lg transition-colors text-black/30 hover:text-brand-blue hover:bg-brand-blue/10">
        <ArchiveRestore class="w-3.5 h-3.5" />
      </button>

      <button @click="$emit('delete', notif.id)"
        :title="isArchived ? 'Delete permanently' : 'Archive notification'"
        class="w-7 h-7 flex items-center justify-center rounded-lg transition-colors text-black/30 hover:text-red-500 hover:bg-red-50">
        <Trash2 class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
