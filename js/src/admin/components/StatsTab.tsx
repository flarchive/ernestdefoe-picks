import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import Week from '../../common/models/Week';

interface MostPickedTeam {
  name: string;
  abbreviation: string;
  picks: number;
}

interface ContestledGame {
  event_id: number;
  home_team: string;
  away_team: string;
  home_pct: number;
  away_pct: number;
  total: number;
}

interface StatsData {
  participation: {
    total_players: number;
    unique_pickers_this_week: number;
    picks_this_week: number;
    total_games_this_week: number;
    participation_rate: number | null;
    users_not_picked_this_week: number | null;
  };
  accuracy: {
    avg_accuracy_all_time: number | null;
    avg_accuracy_this_week: number | null;
    upset_rate: number | null;
    most_picked_team: MostPickedTeam | null;
  };
  coverage: {
    total_finished: number;
    total_scheduled: number;
    games_no_picks: number;
    consensus_games: number;
    most_contested: ContestledGame[];
  };
}

export default class StatsTab extends Component {
  private stats: StatsData | null = null;
  private loading: boolean = false;
  private error: string | null = null;
  private selectedWeekId: string = '';

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    const weeks = app.store.all<Week>('picks-weeks');
    if (weeks.length > 0) {
      this.initWeek();
      this.load();
    } else {
      app.store.find<Week[]>('picks-weeks').then(() => {
        this.initWeek();
        this.load();
      });
    }
  }

  private initWeek() {
    const sorted = app.store.all<Week>('picks-weeks').sort((a, b) => {
      if (a.seasonType() !== b.seasonType()) return a.seasonType() === 'regular' ? -1 : 1;
      return (a.weekNumber() || 0) - (b.weekNumber() || 0);
    });
    if (sorted.length > 0) this.selectedWeekId = String(sorted[0].id());
  }

  private load() {
    if (!this.selectedWeekId) {
      this.loading = false;
      m.redraw();
      return;
    }

    this.loading = true;
    this.error = null;
    m.redraw();

    const params: Record<string, any> = {};
    if (this.selectedWeekId) params.week_id = this.selectedWeekId;

    app.request<StatsData>({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/picks/stats',
      params,
    }).then((r) => {
      this.stats = r;
      this.loading = false;
      m.redraw();
    }).catch(() => {
      this.error = 'Failed to load stats.';
      this.loading = false;
      m.redraw();
    });
  }

  private sortedWeeks(): Week[] {
    return app.store.all<Week>('picks-weeks').sort((a, b) => {
      if (a.seasonType() !== b.seasonType()) return a.seasonType() === 'regular' ? -1 : 1;
      return (a.weekNumber() || 0) - (b.weekNumber() || 0);
    });
  }

  private statCard(icon: string, label: string, value: string | number | null, suffix: string = ''): Mithril.Children {
    const display = value !== null ? String(value) + suffix : '—';
    return (
      <div className="AnalyticsCard" key={label}>
        <div className="AnalyticsCard-icon">
          <i className={icon} />
        </div>
        <div className="AnalyticsCard-body">
          <div className="AnalyticsCard-value">{display}</div>
          <div className="AnalyticsCard-label">{label}</div>
        </div>
      </div>
    );
  }

  view() {
    const weeks = this.sortedWeeks();
    const s = this.stats;

    return (
      <div className="PicksStatsTab">
        <div className="PicksTab-header">
          <div>
            <h3>
              <i className="fas fa-chart-bar" />
              {' '}Stats
            </h3>
            <p className="PicksTab-meta">Pick'em analytics for admins</p>
          </div>
          <div className="PicksTab-actions">
            <select
              className="FormControl"
              value={this.selectedWeekId}
              onchange={(e: Event) => {
                this.selectedWeekId = (e.target as HTMLSelectElement).value;
                this.load();
              }}
            >
              {weeks.map(w => (
                <option key={String(w.id())} value={String(w.id())}>{w.name()}</option>
              ))}
            </select>
            <Button
              className="Button"
              icon="fas fa-sync"
              loading={this.loading}
              onclick={() => this.load()}
            >
              Refresh
            </Button>
          </div>
        </div>

        {this.error && <div className="PicksAlert PicksAlert--error">{this.error}</div>}

        {this.loading ? (
          <LoadingIndicator />
        ) : !this.selectedWeekId ? (
          <div className="PicksEmptyState">
            No schedule synced yet. Sync a schedule from Seasons &amp; Weeks to see stats.
          </div>
        ) : !s ? null : (
          <>
            {/* Participation */}
            <div className="PicksStatsSection">
              <div className="PicksStatsSection-title">
                <i className="fas fa-users" /> Participation
              </div>
              <div className="PicksStats-cards">
                {this.statCard('fas fa-users', 'Total Players', s.participation.total_players)}
                {this.statCard('fas fa-check-circle', 'Picked This Week', s.participation.unique_pickers_this_week)}
                {this.statCard('fas fa-percentage', 'Participation Rate', s.participation.participation_rate, '%')}
                {this.statCard('fas fa-user-clock', 'Yet to Pick', s.participation.users_not_picked_this_week)}
              </div>
            </div>

            {/* Accuracy & Scoring */}
            <div className="PicksStatsSection">
              <div className="PicksStatsSection-title">
                <i className="fas fa-bullseye" /> Accuracy & Scoring
              </div>
              <div className="PicksStats-cards">
                {this.statCard('fas fa-chart-line', 'Avg Accuracy (Season)', s.accuracy.avg_accuracy_all_time, '%')}
                {this.statCard('fas fa-calendar-week', 'Avg Accuracy (This Week)', s.accuracy.avg_accuracy_this_week, '%')}
                {this.statCard('fas fa-bolt', 'Upset Rate', s.accuracy.upset_rate, '%')}
                {this.statCard('fas fa-football', 'Most Picked Team', s.accuracy.most_picked_team?.abbreviation ?? null)}
              </div>
              {s.accuracy.most_picked_team && (
                <p className="PicksStats-footnote">
                  <i className="fas fa-football" />
                  {' '}Most picked team: <strong>{s.accuracy.most_picked_team.name}</strong> — {s.accuracy.most_picked_team.picks.toLocaleString()} picks
                </p>
              )}
            </div>

            {/* Game Coverage */}
            <div className="PicksStatsSection">
              <div className="PicksStatsSection-title">
                <i className="fas fa-clipboard-list" /> Game Coverage
              </div>
              <div className="PicksStats-cards">
                {this.statCard('fas fa-flag-checkered', 'Results Entered', s.coverage.total_finished)}
                {this.statCard('fas fa-clock', 'Awaiting Results', s.coverage.total_scheduled)}
                {this.statCard('fas fa-ghost', 'Games With No Picks', s.coverage.games_no_picks)}
                {this.statCard('fas fa-handshake', 'Consensus Games', s.coverage.consensus_games)}
              </div>

              {s.coverage.most_contested.length > 0 && (
                <div className="PicksStats-contestedList">
                  <div className="PicksStats-contestedTitle">Most Contested Matchups</div>
                  {s.coverage.most_contested.map((g) => (
                    <div className="PicksStats-contestedRow" key={String(g.event_id)}>
                      <span className="PicksStats-contestedMatchup">
                        {g.home_team} vs {g.away_team}
                      </span>
                      <div className="PicksStats-contestedBar">
                        <div
                          className="PicksStats-contestedFill PicksStats-contestedFill--home"
                          style={`width: ${g.home_pct}%`}
                        />
                        <div
                          className="PicksStats-contestedFill PicksStats-contestedFill--away"
                          style={`width: ${g.away_pct}%`}
                        />
                      </div>
                      <span className="PicksStats-contestedSplit">
                        {g.home_pct}% / {g.away_pct}%
                      </span>
                      <span className="PicksStats-contestedTotal">{g.total} picks</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}
      </div>
    );
  }
}
