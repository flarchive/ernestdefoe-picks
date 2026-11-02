import Model from 'flarum/common/Model';

export default class Team extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  abbreviation = Model.attribute<string | null>('abbreviation');
  conference = Model.attribute<string | null>('conference');
  cfbdId = Model.attribute<number | null>('cfbdId');
  espnId = Model.attribute<number | null>('espnId');
  logoPath = Model.attribute<string | null>('logoPath');
  logoDarkPath = Model.attribute<string | null>('logoDarkPath');
  logoCustom = Model.attribute<boolean>('logoCustom');
  logoUrl = Model.attribute<string | null>('logoUrl');
  logoDarkUrl = Model.attribute<string | null>('logoDarkUrl');
}
