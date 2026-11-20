import type { BoardArm, BoardCell, BoardConfig, RoomPlayer } from './room';

/** El tablero de partida añade las bases, las casas y el campo `next`. */
export type GameBoardCell = BoardCell & {
    next: number;
};

export type Point = {
    x: number;
    y: number;
};

export type GameBoardBase = {
    arm: number;
    color: string;
    hex: string;
    occupied: boolean;
    slots: [Point, Point, Point, Point] | Point[];
};

export type GameBoardHome = {
    arm: number;
    color: string;
    hex: string;
    occupied: boolean;
    cells: Point[];
    goals: Point[];
};

export type GameBoard = Omit<BoardConfig, 'cells'> & {
    cells: GameBoardCell[];
    bases: GameBoardBase[];
    homes: GameBoardHome[];
    armsDetail: BoardArm[];
};

/** La lista de jugadores del snapshot usa exactamente la misma forma que la sala. */
export type GamePlayer = RoomPlayer;

export type GamePiece = {
    id: string;
    playerId: number;
    seat: number;
    slot: number;
    /** null = casa, 0..M-1 = anillo, M..M+homeLength-1 = columna, M+homeLength = meta */
    progress: number | null;
};

export type GameStatePlayer = {
    id: number;
    nickname: string;
    color: string;
    seat: number;
    isConnected?: boolean;
};

export type DiceHistoryEntry = {
    playerId: number;
    value: number;
    turn: number;
    consecutiveSixes?: number;
};

export type GameState = {
    players: Record<string, GameStatePlayer>;
    pieces: Record<string, GamePiece>;
    turnOrder: number[];
    currentTurnIndex: number;
    currentPlayerId: number;
    consecutiveSixes: number;
    pendingRoll: number | null;
    legalMoves: string[];
    dice: {
        last: number | null;
        history: DiceHistoryEntry[];
    };
    turns: number;
    totalRolls: number;
    captures: Record<string, number>;
    status: 'in_progress' | 'finished';
    winnerPlayerId: number | null;
    result: Record<string, unknown> | GameResult | null;
};

export type GameSnapshot = {
    gameId: number;
    code: string;
    status: 'in_progress' | 'finished';
    arms: number;
    board: GameBoard;
    players: GamePlayer[];
    youPlayerId: number | null;
    state: GameState;
    version: number;
    currentPlayerId: number | null;
    pendingRoll: number | null;
    legalMoves: string[];
    result: Record<string, unknown> | GameResult | null;
};

export type ResultWinner = {
    id: number;
    nickname: string;
    color: string;
};

export type ResultRankingEntry = {
    playerId: number;
    nickname: string;
    color: string;
    seat: number;
    pieces_meta: number;
    progress_total: number;
    captures: number;
};

export type GameResult = {
    game_code: string;
    status: 'finished' | 'en_curso';
    board_arms: number;
    winner: ResultWinner | null;
    ranking: ResultRankingEntry[];
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    turns: number;
    total_rolls: number;
    total_captures: number;
    captures_by_player: Record<string, number>;
    message?: string;
};

export type GameHistoryEntry = {
    code: string;
    winner?: string | ResultWinner | null;
    nickname?: string;
    color?: string;
    playerCount?: number;
    durationSeconds?: number;
    finishedAt?: string;
};

export type CapturedPiece = {
    pieceId: string;
    playerId: number;
    cell?: number;
};

/** Eventos del canal `private-game.{gameId}` (nombres cortos, con punto inicial en Echo Vue). */
export type GameStartedEventPayload = {
    snapshot: GameSnapshot;
    message?: string;
};
export type DiceRolledEventPayload = {
    snapshot: GameSnapshot;
    playerId: number;
    value: number;
    consecutiveSixes?: number;
};
export type PieceMovedEventPayload = {
    snapshot: GameSnapshot;
    pieceId: string;
    playerId: number;
    from: number | null;
    to: number;
};
export type CaptureOccurredEventPayload = {
    snapshot: GameSnapshot;
    playerId: number;
    captured: CapturedPiece[];
};
export type TurnAdvancedEventPayload = {
    snapshot: GameSnapshot;
    fromPlayerId?: number;
    playerId: number;
    reason?: string;
};
export type GameStateUpdatedEventPayload = {
    snapshot: GameSnapshot;
    reason?: string;
};
export type GameFinishedEventPayload = {
    snapshot: GameSnapshot;
    result?: Record<string, unknown> | GameResult;
};
export type RematchCreatedEventPayload = {
    oldCode: string;
    newCode: string;
    message?: string;
};

export type GameEventPayload =
    | GameStartedEventPayload
    | DiceRolledEventPayload
    | PieceMovedEventPayload
    | CaptureOccurredEventPayload
    | TurnAdvancedEventPayload
    | GameStateUpdatedEventPayload
    | GameFinishedEventPayload
    | RematchCreatedEventPayload;

export type GameEventType =
    | 'GameStartedForGame'
    | 'DiceRolled'
    | 'PieceMoved'
    | 'CaptureOccurred'
    | 'TurnAdvanced'
    | 'GameStateUpdated'
    | 'GameFinished'
    | 'RematchCreated';

export type RematchResponse = {
    newCode: string;
    message?: string;
};

export type RematchTokenResponse = {
    code: string;
    playerToken: string;
};
