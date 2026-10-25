import type { HTMLAttributes } from 'react'
import { cn } from '../../lib/utils'

export function Badge({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border border-moss-700 bg-moss-800 px-2 py-0.5 text-[11px] text-mist/80',
        className,
      )}
      {...props}
    />
  )
}
