import { reactive } from 'vue'
import router from '@/router';

const getBasePath = () => router.options.history.base || '/';

// Shared login state. The server tells us the initial value when it renders
// the page; http.ts flips it to false when an API call comes back 401
// (e.g. the session expired).
export const auth = reactive({
  loggedIn: !!window.APP.AUTHENTICATED,
})

export interface AuthResult {
  success: boolean
  message?: string
}

export async function login(password: string): Promise<AuthResult> {
  const res = await fetch(`${getBasePath()}/api/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ password }),
  })
  const data: AuthResult = await res.json()

  if (data.success) auth.loggedIn = true
  return data
}

export async function logout(): Promise<void> {
  try {
    await fetch(`${getBasePath()}/api/logout`, { method: 'POST' })
  } finally {
    // Show the login page even if the request itself failed
    auth.loggedIn = false
  }
}
