import { __, sprintf } from "@wordpress/i18n";
import { Button, Notice, SelectControl, Spinner, TextControl } from "@wordpress/components";

export default function MenuGroups({ config, location, catalog, loading, error, onChange }) {
  if (config.location_id !== undefined && config.location_id !== location) {
    return (
      <>
        <Notice status="warning" isDismissible={false}>
          {__("The saved groups belong to a different location.", "sp-menu")}
        </Notice>
        <Button variant="secondary" onClick={() => onChange({ location_id: location, groups: [] })}>
          {__("Replace groups for this location", "sp-menu")}
        </Button>
      </>
    );
  }

  const groups = (Array.isArray(config.groups) ? config.groups : [])
    .filter((group) => group && Array.isArray(group.members))
    .map(({ label, members }) => ({
      label: typeof label === "string" ? label : "",
      members: members.filter((member) => typeof member?.menu_id === "string").map((member) => ({
        menu_id: member.menu_id,
        label: typeof member.label === "string" ? member.label : "",
      })),
    }));
  const assigned = new Set(groups.flatMap((group) => group.members.map((member) => member.menu_id)));
  const setGroups = (next) => onChange({ location_id: location, groups: next });
  const setGroup = (index, changes) => setGroups(
    groups.map((group, position) => position === index ? { ...group, ...changes } : group)
  );

  return (
    <>
      {!location && <p>{__("Enter a Location ID to select menus.", "sp-menu")}</p>}
      {loading && <Spinner />}
      {error && (
        <Notice status="warning" isDismissible={false}>
          {__("Menus could not be loaded for this location.", "sp-menu")}
        </Notice>
      )}
      {groups.map((group, groupIndex) => {
        const setMembers = (members) => setGroup(groupIndex, { members });
        const setMember = (index, changes) => setMembers(group.members.map(
          (member, position) => position === index ? { ...member, ...changes } : member
        ));
        const moveMember = (from, to) => {
          const members = [...group.members];
          [members[from], members[to]] = [members[to], members[from]];
          setMembers(members);
        };

        return (
          <fieldset key={groupIndex}>
            <legend>{sprintf(__("Group %d", "sp-menu"), groupIndex + 1)}</legend>
            <TextControl
              label={__("Group label", "sp-menu")}
              value={group.label}
              onChange={(label) => setGroup(groupIndex, { label })}
            />
            {group.members.map((member, index) => {
              const available = catalog.some(({ id }) => id === member.menu_id);
              const options = [{ label: __("Select a menu", "sp-menu"), value: "" }];
              if (member.menu_id && !available) {
                options.push({
                  label: sprintf(__("Saved menu ID: %s", "sp-menu"), member.menu_id),
                  value: member.menu_id,
                });
              }
              for (const { id, name } of catalog) {
                if (id === member.menu_id || !assigned.has(id)) {
                  options.push({ label: name, value: id });
                }
              }

              return (
                <fieldset key={index}>
                  <legend>{sprintf(__("Menu %d", "sp-menu"), index + 1)}</legend>
                  <SelectControl
                    label={__("SinglePlatform menu", "sp-menu")}
                    value={member.menu_id}
                    options={options}
                    disabled={!location || loading || error}
                    help={location && member.menu_id && !available && !loading && !error
                      ? __("This menu is unavailable at this location.", "sp-menu")
                      : undefined}
                    onChange={(menu_id) => setMember(index, { menu_id })}
                  />
                  <TextControl
                    label={__("Display label (optional)", "sp-menu")}
                    value={member.label}
                    onChange={(label) => setMember(index, { label })}
                  />
                  <Button variant="tertiary" disabled={index === 0} onClick={() => moveMember(index, index - 1)}>
                    {__("Move up", "sp-menu")}
                  </Button>
                  <Button variant="tertiary" disabled={index === group.members.length - 1} onClick={() => moveMember(index, index + 1)}>
                    {__("Move down", "sp-menu")}
                  </Button>
                  <Button isDestructive variant="tertiary" onClick={() => setMembers(group.members.filter((_, position) => position !== index))}>
                    {__("Remove menu", "sp-menu")}
                  </Button>
                </fieldset>
              );
            })}
            <Button variant="secondary" onClick={() => setMembers([...group.members, { menu_id: "", label: "" }])}>
              {__("Add menu", "sp-menu")}
            </Button>
            <Button isDestructive variant="tertiary" onClick={() => setGroups(groups.filter((_, position) => position !== groupIndex))}>
              {__("Remove group", "sp-menu")}
            </Button>
          </fieldset>
        );
      })}
      <Button variant="secondary" disabled={!location} onClick={() => setGroups([...groups, { label: "", members: [] }])}>
        {__("Add group", "sp-menu")}
      </Button>
    </>
  );
}
