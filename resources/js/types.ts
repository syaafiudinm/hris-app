export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: "super_admin" | "manager" | "employee";
    isManagement: boolean;
    isSuperAdmin: boolean;
};

export type AuthEmployee = {
    id: number;
    nik: string;
    position: string | null;
    employmentType: string | null;
    category: "probation" | "pkwt" | "mitra" | null;
    isLeaveEligible: boolean;
    isBpjsEligible: boolean;
};

export type PageProps = {
    appName: string;
    auth: {
        user: AuthUser | null;
        employee: AuthEmployee | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
};

export const ROLE_LABELS: Record<AuthUser["role"], string> = {
    super_admin: "Super Admin / HR",
    manager: "Manager / Atasan",
    employee: "Employee / Mitra",
};
