import { reactive } from 'vue'
import type { Contract, UploadedDoc } from '@/types/contract'

export interface StoredContract extends Contract {
  docs: UploadedDoc[]
}

const store = reactive<Map<string, StoredContract>>(new Map())
let _counter = 100

export function useContractStore() {
  function generateId(): string {
    return `CTR-${String(++_counter).padStart(3, '0')}`
  }

  function save(contract: StoredContract): void {
    store.set(contract.id, contract)
  }

  function get(id: string): StoredContract | undefined {
    return store.get(id)
  }

  function update(id: string, patch: Partial<Omit<StoredContract, 'id' | 'docs'>>): void {
    const existing = store.get(id)
    if (existing) Object.assign(existing, patch)
  }

  function updateDocs(id: string, docs: UploadedDoc[]): void {
    const existing = store.get(id)
    if (existing) existing.docs = docs
  }

  return { store, generateId, save, get, update, updateDocs }
}
