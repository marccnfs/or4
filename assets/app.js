import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const startPolling = (url, onSuccess, interval = 3000) => {
    if (!url) {
        return;
    }

    const load = () => {
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload) {
                    onSuccess(payload);
                }
            })
            .catch(() => {});
    };

    load();
    setInterval(load, interval);
};

const setupPilotTeams = () => {
    const root = document.querySelector('[data-pilot-teams-url]');
    if (!root) {
        return;
    }

    const list = root.querySelector('[data-pilot-team-list]');
    const count = root.querySelector('[data-pilot-team-count]');
    const updated = root.querySelector('[data-pilot-team-updated]');

    startPolling(root.dataset.pilotTeamsUrl, (payload) => {
        if (count) {
            count.textContent = payload.count ?? '0';
        }
        if (updated) {
            updated.textContent = payload.updated_at
                ? `Dernière mise à jour : ${payload.updated_at}`
                : '';
        }
        if (!list) {
            return;
        }

        list.innerHTML = '';
        if (!payload.teams || payload.teams.length === 0) {
            const item = document.createElement('li');
            item.textContent = 'Aucune équipe inscrite pour le moment.';
            item.classList.add('pilot-teams__empty');
            list.appendChild(item);
            return;
        }

        payload.teams.forEach((team) => {
            const item = document.createElement('li');
            const name = document.createElement('span');
            name.textContent = team.name;

            const code = document.createElement('span');
            code.classList.add('team-list__code');
            code.textContent = team.code;

            const state = document.createElement('span');
            state.classList.add('team-list__state');
            state.textContent = team.state;

            const qr = document.createElement('span');
            qr.classList.add('team-list__qr');
            if (team.qr_total && team.qr_total > 0) {
                qr.textContent = `QR ${team.qr_scanned ?? 0}/${team.qr_total}`;
            } else {
                qr.textContent = 'QR —';
            }

            item.appendChild(name);
            item.appendChild(code);
            item.appendChild(state);
            item.appendChild(qr);
            list.appendChild(item);
        });
    });
};

const setupScoreboard = () => {
    const root = document.querySelector('[data-scoreboard-url]');
    if (!root) {
        return;
    }

    const rows = root.querySelector('[data-scoreboard-rows]');
    const total = root.querySelector('[data-scoreboard-total]');
    const status = root.querySelector('[data-scoreboard-status]');
    const updated = root.querySelector('[data-scoreboard-updated]');

    startPolling(root.dataset.scoreboardUrl, (payload) => {
        if (status) {
            status.textContent = payload.status ?? '';
        }
        if (total) {
            total.textContent = payload.total_steps ?? '0';
        }
        if (updated) {
            updated.textContent = payload.updated_at
                ? `Mis à jour à ${payload.updated_at}`
                : '';
        }
        if (!rows) {
            return;
        }

        rows.innerHTML = '';
        if (!payload.teams || payload.teams.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.classList.add('scoreboard-empty');
            cell.textContent = 'Aucune équipe inscrite pour le moment.';
            row.appendChild(cell);
            rows.appendChild(row);
            return;
        }

        const totalSteps = payload.total_steps ?? 0;

        payload.teams.forEach((team) => {
            const row = document.createElement('tr');

            const name = document.createElement('td');
            name.textContent = team.name;

            const code = document.createElement('td');
            code.textContent = team.code;

            const progressCell = document.createElement('td');
            const bar = document.createElement('div');
            bar.classList.add('scoreboard-progress');
            const barFill = document.createElement('span');
            const percent = totalSteps > 0 ? Math.min((team.validated_steps / totalSteps) * 100, 100) : 0;
            barFill.style.width = `${percent}%`;
            bar.appendChild(barFill);

            const meta = document.createElement('div');
            meta.classList.add('scoreboard-progress-meta');
            meta.textContent = `${team.validated_steps}/${totalSteps}`;

            progressCell.appendChild(bar);
            progressCell.appendChild(meta);

            const lastUpdate = document.createElement('td');
            lastUpdate.textContent = team.last_update || '—';

            row.appendChild(name);
            row.appendChild(code);
            row.appendChild(progressCell);
            row.appendChild(lastUpdate);
            rows.appendChild(row);
        });
    }, 3000);
};

document.addEventListener('DOMContentLoaded', () => {
    setupPilotTeams();
    setupScoreboard();
});
