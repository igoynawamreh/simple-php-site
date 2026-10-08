import { auth } from '@/services/auth'

// `fetch()` for the panel API. A 401 means the session is gone, so
// flip the login state and "App.vue" shows the login page.
export async function request(input: string, init?: RequestInit): Promise<Response> {
  const res = await fetch(input, init)

  if (res.status === 401) auth.loggedIn = false

  return res;
}
