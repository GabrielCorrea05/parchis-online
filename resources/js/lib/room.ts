import type { Room } from '@/types';

/**
 * Los eventos de sala viajan con un payload neutro (no personalizado). Cada
 * pestaña recupera su identidad con este helper: el host conserva `canStart`
 * aunque otro jugador entre, y el que se une nunca ve el botón del host.
 */
export function personaliseRoom(room: Room, youPlayerId: number | null): Room {
    const canStart =
        room.status === 'waiting' &&
        youPlayerId !== null &&
        room.hostPlayerId === youPlayerId &&
        room.playerCount >= room.minPlayers &&
        room.playerCount <= room.maxPlayers;

    return {
        ...room,
        currentPlayerId: youPlayerId,
        canStart,
        players: room.players.map((player) => ({
            ...player,
            isYou: player.id === youPlayerId,
        })),
    };
}
