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
    invite_code: string | null;
    invited_by: string | null;
    specimens_count: number;
    created_at: string | null;
};
