import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import type Mithril from 'mithril';
import TeamsTab from './TeamsTab';
import SeasonsTab from './SeasonsTab';
import GamesTab from './GamesTab';
import SyncSettingsTab from './SyncSettingsTab';
import PicksSettingsTab from './PicksSettingsTab';
import LeaderboardTab from './LeaderboardTab';
import StatsTab from './StatsTab';

export default class PicksPage extends ExtensionPage {
  private activeTab: string = 'teams';

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    const param = m.route.param('tab');
    const validTabs = ['teams', 'sync', 'seasons', 'games', 'scores', 'settings'];
    if (param && validTabs.includes(param)) {
      this.activeTab = param;
    }
  }

  content(): Mithril.Children {
    return (
      <div className="ExtensionPage-settings">
        <div className="container">
          <div className="PicksAdminPage">
            <div className="PicksAdminPage-tabs">
              {this.renderTab('teams',    'fas fa-users',        'resofire-picks.admin.nav.teams')}
              {this.renderTab('sync',     'fas fa-sync',         'resofire-picks.admin.nav.sync')}
              {this.renderTab('seasons',  'fas fa-calendar-alt', 'resofire-picks.admin.nav.seasons')}
              {this.renderTab('games',    'fas fa-football',     'resofire-picks.admin.nav.games')}
              {this.renderTab('scores',   'fas fa-trophy',       'resofire-picks.admin.nav.scores')}
              {this.renderTab('stats',    'fas fa-chart-bar',    'resofire-picks.admin.nav.stats')}
              {this.renderTab('settings', 'fas fa-cog',          'resofire-picks.admin.nav.settings')}
            </div>

            <div className="PicksAdminPage-content">
              {this.renderActiveTab()}
            </div>
          </div>
        </div>
      </div>
    );
  }

  private renderTab(key: string, icon: string, translationKey: string): Mithril.Children {
    const isActive = this.activeTab === key;

    return (
      <button
        className={`Button PicksAdminPage-tab ${isActive ? 'Button--primary' : ''}`}
        onclick={() => {
          this.activeTab = key;
          m.redraw();
        }}
      >
        <i className={icon} />
        {' '}
        {app.translator.trans(translationKey)}
      </button>
    );
  }

  private renderActiveTab(): Mithril.Children {
    switch (this.activeTab) {
      case 'teams':
        return <TeamsTab />;
      case 'seasons':
        return <SeasonsTab />;
      case 'games':
        return <GamesTab />;
      case 'sync':
        return <SyncSettingsTab />;
      case 'stats':
        return <StatsTab />;
      case 'settings':
        return <PicksSettingsTab />;
      case 'scores':
        return <LeaderboardTab />;
      default:
        return <TeamsTab />;
    }
  }
}
