/** Padrões e faixas do gerador de convite, vindos do `config/game.php`. */
export type InviteForm = {
    defaultPrefix: string;
    prefixMaxLength: number;
    alphabet: string;
    suffixLength: number;
    maxUses: { min: number; max: number };
    validities: string[];
    defaultValidity: string;
};

export type Player = {
    id: number;
    nickname: string | null;
    tag: string | null;
    handle: string;
    email: string;
    avatar_sprite_path: string | null;
    is_admin: boolean;
    invite_quota: number;
    is_email_verified: boolean;
    is_birthdate_locked: boolean;
    has_pack_available: boolean;
    invite_code: string | null;
    invited_by: string | null;
    specimens_count: number;
    created_at: string | null;
};

export type InviteStatus = 'active' | 'exhausted' | 'expired' | 'revoked';

export type Invite = {
    id: number;
    code: string;
    status: InviteStatus;
    max_uses: number;
    uses_count: number;
    expires_at: string | null;
    created_at: string;
    created_by: string;
    redemptions: {
        nickname: string;
        handle: string;
        avatar_sprite_path: string | null;
    }[];
};

export const inviteStatusMeta: Record<
    InviteStatus,
    { label: string; plural: string; pill: string }
> = {
    active: {
        label: 'Ativo',
        plural: 'Ativos',
        pill: 'bg-[#e3f1ea] text-[#2f7556]',
    },
    exhausted: {
        label: 'Esgotado',
        plural: 'Esgotados',
        pill: 'bg-[#eceef1] text-[#656b74]',
    },
    expired: {
        label: 'Expirado',
        plural: 'Expirados',
        pill: 'bg-[#f6e9df] text-[#8f5735]',
    },
    revoked: {
        label: 'Revogado',
        plural: 'Revogados',
        pill: 'bg-[#ffe6e6] text-[#d63c3c]',
    },
};
