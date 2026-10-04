import router from '@/router';
import { request } from '@/lib/http';

const getBasePath = () => router.options.history.base || '/';

export interface ListParams {
  page?: number
  count?: number
  q?: string
  category?: string
  tags?: string[]
  order_by?: string
  order_dir?: string
}

export interface Data {
  slug?: string
  title: string
  date?: string
  category?: string
  tags?: string[]
  thumbnail?: string
  body?: string
}

// Field errors are keyed by field name ('title', 'slug', 'date', ...)
export type FieldErrors = Record<string, string | string[]>

export interface ApiResult {
  success: boolean
  message?: string
  errors?: FieldErrors
}

export interface ReadResponse extends ApiResult {
  data?: {
    route: string
    slug: string
    title: string
    date: string | null
    category: string | null
    tags: string[] | null
    thumbnail: string | null
    body: string | null
  }
}

export interface SaveResponse extends ApiResult {
  slug?: string
}

export interface ListData {
  slug: string
  title: string
  date: string | null
  category: string | null
  tags: string[]
}

export interface ListResponse {
  success: boolean
  message?: string
  data: ListData[]
  meta: {
    total: number
    count: number
    current_page: number
    last_page: number
  }
  // Page numbers; '...' marks a gap
  pagination: (number | '...')[]
  // Every available filter option
  fields: {
    category: string[]
    tags: string[]
  }
}

function toFormData(data: Data): FormData {
  const form = new FormData()
  if (data.slug) form.append('slug', data.slug)
  form.append('title', data.title)
  if (data.date) form.append('date', data.date)
  if (data.category) form.append('category', data.category)
  if (data.thumbnail) form.append('thumbnail', data.thumbnail)
  if (data.body) form.append('body', data.body)
  for (const tag of data.tags ?? []) {
    form.append('tags[]', tag)
  }
  return form
}

export async function getPages(configRoutePath: string, params: ListParams = {}): Promise<ListResponse> {
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) {
      value.forEach((v) => query.append(`${key}[]`, v))
    } else {
      query.set(key, String(value))
    }
  }
  query.set('routePath', configRoutePath);
  const res = await request(`${getBasePath()}/api/page-list?${query}`)
  return res.json()
}

export async function getPage(configRoutePath: string, slug: string): Promise<ReadResponse> {
  const res = await request(`${getBasePath()}/api/page-read?slug=${encodeURIComponent(slug)}&routePath=${encodeURIComponent(configRoutePath)}`)
  return res.json()
}

export async function createPage(configRoutePath: string, data: Data): Promise<SaveResponse> {
  const res = await request(`${getBasePath()}/api/page-create?routePath=${encodeURIComponent(configRoutePath)}`, { method: 'POST', body: toFormData(data) })
  return res.json()
}

export async function updatePage(configRoutePath: string, data: Data): Promise<SaveResponse> {
  const res = await request(`${getBasePath()}/api/page-update?routePath=${encodeURIComponent(configRoutePath)}`, { method: 'POST', body: toFormData(data) })
  return res.json()
}

export async function deletePage(configRoutePath: string, slug: string): Promise<ApiResult> {
  const form = new FormData()
  form.append('slug', slug)
  const res = await request(`${getBasePath()}/api/page-delete?routePath=${encodeURIComponent(configRoutePath)}`, { method: 'POST', body: form })
  return res.json()
}
