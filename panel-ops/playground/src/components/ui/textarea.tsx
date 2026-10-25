import type { TextareaHTMLAttributes } from 'react'
import { cn } from '../../lib/utils'

export function Textarea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return (
    <textarea
      className={cn(
        'min-h-[88px] w-full resize-none rounded-xl border border-moss-700 bg-moss-950/80 px-3 py-2 text-sm text-mist placeholder:text-mist/40 focus:border-gold-400 focus:outline-none',
        className,
      )}
      {...props}
    />
  )
}
