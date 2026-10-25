import type { InputHTMLAttributes } from 'react'
import { cn } from '../../lib/utils'

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      className={cn(
        'h-10 w-full rounded-lg border border-moss-700 bg-moss-950/80 px-3 text-sm text-mist placeholder:text-mist/40 focus:border-gold-400 focus:outline-none',
        className,
      )}
      {...props}
    />
  )
}
