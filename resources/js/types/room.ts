export type ColorOption = {
    name: string;
    hex: string;
};

export type RoomPlayer = {
    id: number;
    nickname: string;
    color: string;
    seat: number;
    isHost: boolean;
    isConnected: boolean;
    isYou: boolean;
};

export type BoardCell = {
    index: number;
    arm: number;
    kind: 'salida' | 'normal';
    safe: boolean;
    occupied: boolean;
    x: number;
    y: number;
};

export type BoardArm = {
    index: number;
    angle: number;
    color: string;
    hex: string;
    occupied: boolean;
    salidaIndex: number;
    base: { x: number; y: number };
    entrance: { x: number; y: number };
};

export type BoardConfig = {
    arms: number;
    cellsPerArm: number;
    homeLength: number;
    geometry: string;
    viewBox: string;
    center: { x: number; y: number };
    cellSize: number;
    palette: Record<string, string>;
    armsDetail: BoardArm[];
    cells: BoardCell[];
    occupiedArms: number[];
    safeCells: number[];
};

export type Room = {
    code: string;
    status: 'waiting' | 'in_progress' | 'finished';
    arms: number | null;
    board: BoardConfig;
    players: RoomPlayer[];
    hostPlayerId: number | null;
    playerCount: number;
    minPlayers: number;
    maxPlayers: number;
    currentPlayerId: number | null;
    canStart: boolean;
};
