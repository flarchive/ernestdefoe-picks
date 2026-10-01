import Extend from 'flarum/common/extenders';
import PicksPage from './components/PicksPage';
import UserPicksPage from './components/UserPicksPage';
import Week from '../common/models/Week';
import Season from '../common/models/Season';

export default [
  new Extend.Store()
    .add('picks-weeks', Week)
    .add('picks-seasons', Season),

  new Extend.Routes()
    .add('picks', '/picks', PicksPage)
    .add('picks.week', '/picks/week/:weekId', PicksPage)
    .add('user.picks-history', '/u/:username/picks-history', UserPicksPage),
];
