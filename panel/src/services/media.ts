import router from '@/router';
import { request } from '@/lib/http'

const getBasePath = () => router.options.history.base || '/';

export type MediaType = 'image' | 'document' | 'other'

export interface MediaItem {
  name: string
  // Site-relative URL, e.g. /media/photo.jpg
  url: string
  ext: string
  type: MediaType
  // Bytes
  size: number
  // Unix timestamp (seconds)
  modified: number
}

export interface ListParams {
  page?: number
  count?: number
  q?: string
  type?: MediaType | ''
}

export interface ListResponse {
  success: boolean
  message?: string
  items: MediaItem[]
  meta: {
    total: number
    count: number
    current_page: number
    last_page: number
  }
  limits: {
    // Largest single file the server accepts, in bytes
    max_size: number
    // Allowed extensions, without the dot
    extensions: string[]
  }
}

export interface UploadFileResult {
  file: string
  success: boolean
  message?: string
  item?: MediaItem
}

export interface UploadResponse {
  success: boolean
  message?: string
  // One entry per file; missing when the whole request was rejected
  results?: UploadFileResult[]
}

export interface DeleteResponse {
  success: boolean
  message?: string
}

export async function listMedia(params: ListParams = {}): Promise<ListResponse> {
  const query = new URLSearchParams()

  if (params.page) query.set('page', String(params.page))
  if (params.count) query.set('count', String(params.count))
  if (params.q) query.set('q', params.q)
  if (params.type) query.set('type', params.type)

  const res = await request(`${getBasePath()}/api/media-list?${query}`)
  return res.json()
}

export async function uploadMedia(files: File[]): Promise<UploadResponse> {
  const form = new FormData()
  for (const file of files) form.append('files[]', file)

  const res = await request(`${getBasePath()}/api/media-upload`, { method: 'POST', body: form })

  try {
    return await res.json()
  } catch {
    // e.g. the web server rejected a very large body with an HTML page
    return {
      success: false,
      message: `Upload failed (HTTP ${res.status}). The files may be larger than the server allows.`,
    }
  }
}

export async function deleteMedia(name: string): Promise<DeleteResponse> {
  const form = new FormData()
  form.append('name', name)

  const res = await request(`${getBasePath()}/api/media-delete`, { method: 'POST', body: form })
  return res.json()
}
