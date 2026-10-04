import { clsx } from "clsx";
import type { ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function joinPath(...parts: Array<string | null | undefined>): string {
  const path = parts
    .filter((p): p is string => Boolean(p))
    .map((p) => p.replace(/^\/+|\/+$/g, ''))
    .filter(Boolean)
    .join('/')
    .replace(/\/{2,}/g, '/');

  return `/${path}`;
}
