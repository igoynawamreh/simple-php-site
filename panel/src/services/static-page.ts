import router from '@/router';
import { request } from '@/lib/http';

const getBasePath = () => router.options.history.base || '/';

export interface Data {
  title: string
  date?: string
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
    body: string | null
  }
}

export interface SaveResponse extends ApiResult {
  slug?: string
}

function toFormData(data: Data): FormData {
  const form = new FormData()
  form.append('title', data.title)
  if (data.date) form.append('date', data.date)
  if (data.body) form.append('body', data.body)
  return form
}

export async function getPage(configRoutePath: string, configContentPath: string): Promise<ReadResponse> {
  const res = await request(`${getBasePath()}/api/static-page-read?routePath=${encodeURIComponent(configRoutePath)}&contentPath=${encodeURIComponent(configContentPath)}`)
  return res.json()
}

export async function updatePage(configRoutePath: string, configContentPath: string, data: Data): Promise<SaveResponse> {
  const res = await request(`${getBasePath()}/api/static-page-update?routePath=${encodeURIComponent(configRoutePath)}&contentPath=${encodeURIComponent(configContentPath)}`, { method: 'POST', body: toFormData(data) })
  return res.json()
}
