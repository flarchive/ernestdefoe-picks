import Model from 'flarum/common/Model';

export default class Season extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  year = Model.attribute<number>('year');
  startDate = Model.attribute<string | null>('startDate');
  endDate = Model.attribute<string | null>('endDate');
}
