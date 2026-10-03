import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

export default class TestingTab extends Component {
  private running: string | null = null;
  private result: string | null = null;
  private resultIsError: boolean = false;

  view(): Mithril.Children {
    return (
      <div className="PicksTestingTab">
        <div className="PicksTab-header">
          <div>
            <h3>
              <i className="fas fa-flask" />
              {' '}Testing Tools
            </h3>
            <p className="PicksTab-meta">
              Populate the database with fake data so you can test the UI without real picks.
              Remove all fake data when testing is complete.
            </p>
          </div>
        </div>

        <div className="PicksDangerZone" style="border-color: rgba(243,156,18,0.4); background: rgba(243,156,18,0.04);">
          <h4 className="PicksDangerZone-title" style="color: #b7770d;">
            <i className="fas fa-exclamation-triangle" />
            {' '}Development use only — remove before production
          </h4>

          <div className="PicksDangerZone-actions">

            <div className="PicksDangerZone-action">
              <div>
                <strong>Seed 2026 Picks</strong>
                <p>
                  Creates fake picks for all users against your real Week 1 and Week 2 events.
                  Finished games are scored; active week picks are left pending.
                  Skips picks that already exist — safe to run multiple times.
                </p>
              </div>
              <Button
                className="Button Button--primary"
                loading={this.running === 'seed2026'}
                disabled={this.running !== null}
                onclick={() => this.run('seed2026')}
              >
                <i className="fas fa-football" /> Seed 2026 Picks
              </Button>
            </div>

            <div className="PicksDangerZone-action">
              <div>
                <strong>Add Fake 2025 Season</strong>
                <p>
                  Fabricates a complete 2025 season — 16 weeks, 8 games each — using your
                  real teams as matchups. All games are marked finished so the history stack
                  has data to display. Run "Seed 2026 Picks" first.
                </p>
              </div>
              <Button
                className="Button Button--primary"
                loading={this.running === 'seedFake2025'}
                disabled={this.running !== null}
                onclick={() => this.run('seedFake2025')}
              >
                <i className="fas fa-calendar-alt" /> Add Fake 2025 Season
              </Button>
            </div>

            <div className="PicksDangerZone-action">
              <div>
                <strong>Clean Fake 2025 Data</strong>
                <p>
                  Removes the fabricated 2025 season, its weeks, fake events, and all associated
                  picks and scores. All-time scores are recalculated. Real 2026 data is untouched.
                </p>
              </div>
              <Button
                className="Button Button--danger"
                loading={this.running === 'cleanFake'}
                disabled={this.running !== null}
                onclick={() => this.confirmAndRun(
                  'cleanFake',
                  'This will delete the fake 2025 season and all associated data. Real 2026 data is safe. Continue?'
                )}
              >
                <i className="fas fa-broom" /> Clean Fake 2025 Data
              </Button>
            </div>

            <div className="PicksDangerZone-action PicksDangerZone-action--severe">
              <div>
                <strong>Wipe All Seeded Data</strong>
                <p>
                  Removes all picks and scores created by this tool across both 2026 and the
                  fake 2025 season. Real events, weeks, and seasons are never deleted.
                </p>
              </div>
              <Button
                className="Button Button--danger"
                loading={this.running === 'wipeAll'}
                disabled={this.running !== null}
                onclick={() => this.confirmAndRun(
                  'wipeAll',
                  'This will wipe all seeded picks and scores. Real schedule data (events/weeks/seasons) will not be touched. Continue?'
                )}
              >
                <i className="fas fa-trash" /> Wipe All Seeded Data
              </Button>
            </div>

          </div>

          {this.result && (
            <div
              className={`PicksAlert ${this.resultIsError ? 'PicksAlert--error' : 'PicksAlert--info'}`}
              style="margin-top: 16px;"
            >
              {this.result}
            </div>
          )}
        </div>
      </div>
    );
  }

  private run(action: string): void {
    this.running = action;
    this.result  = null;
    m.redraw();

    app.request<{ status: string; message: string }>({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/picks/seed-test-data',
      body: { action },
    }).then((r) => {
      this.resultIsError = r.status !== 'success';
      this.result        = (this.resultIsError ? '❌ ' : '✅ ') + r.message;
      this.running       = null;
      m.redraw();
    }).catch(() => {
      this.resultIsError = true;
      this.result        = '❌ Request failed. Check server logs.';
      this.running       = null;
      m.redraw();
    });
  }

  private confirmAndRun(action: string, message: string): void {
    if (!window.confirm(message)) return;
    this.run(action);
  }
}
