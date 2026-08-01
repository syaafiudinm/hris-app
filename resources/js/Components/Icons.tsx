import type { SVGProps } from "react";

type IconProps = SVGProps<SVGSVGElement>;

function base(props: IconProps) {
    return {
        viewBox: "0 0 24 24",
        fill: "none",
        stroke: "currentColor",
        strokeWidth: 1.6,
        strokeLinecap: "round" as const,
        strokeLinejoin: "round" as const,
        "aria-hidden": true,
        ...props,
    };
}

export function IconGrid(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
        </svg>
    );
}

export function IconUsers(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M16 19v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V19" />
            <circle cx="9" cy="7" r="3.2" />
            <path d="M22 19v-1.5a4 4 0 0 0-3-3.87" />
            <path d="M16 4.13a4 4 0 0 1 0 5.74" />
        </svg>
    );
}

export function IconClock(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        </svg>
    );
}

export function IconWallet(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z" />
            <path d="M3 9h18" />
            <circle cx="16.5" cy="14" r="1.1" fill="currentColor" stroke="none" />
        </svg>
    );
}

export function IconFunnel(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M3 5h18l-7 8v6l-4 2v-8z" />
        </svg>
    );
}

export function IconBook(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z" />
            <path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5A2.5 2.5 0 0 1 4 20.5z" />
        </svg>
    );
}

export function IconDownload(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 3v11" />
            <path d="m8 11 4 4 4-4" />
            <path d="M4 19h16" />
        </svg>
    );
}

export function IconAlert(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 4.5 2.8 20h18.4z" />
            <path d="M12 10v4" />
            <circle cx="12" cy="17" r="0.6" fill="currentColor" stroke="none" />
        </svg>
    );
}

export function IconCheck(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12.2 2.4 2.4 4.6-5" />
        </svg>
    );
}

export function IconArrowUp(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 19V5" />
            <path d="m6 11 6-6 6 6" />
        </svg>
    );
}

export function IconArrowDown(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 5v14" />
            <path d="m6 13 6 6 6-6" />
        </svg>
    );
}

export function IconShield(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 3 5 6v6c0 4.2 2.9 7.6 7 9 4.1-1.4 7-4.8 7-9V6z" />
            <path d="m9.2 12 2 2 3.6-4" />
        </svg>
    );
}
