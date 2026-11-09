import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody,
  TextControl,
  ToggleControl,
  SelectControl,
  RangeControl,
  Button,
  Spinner,
  BaseControl,
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import ServerSideRender from "@wordpress/server-side-render";
import apiFetch from "@wordpress/api-fetch";
import MenuGroups from "./menu-groups";
import { handleMenuInteraction } from "./menu-interaction";

export default function Edit({ attributes, setAttributes }) {
  const {
    location_id,
    menu_name,
    enable_menu_groups,
    menu_groups = {},
    category_filter = [],
    highlighted_items = [],
    show_prices,
    currency,
    cache_ttl,
    expand_behavior,
    category_display,
    layout,
    nutrition_visibility,
    labels_visibility,
    item_columns,
  } = attributes;

  const location = location_id.trim();
  const [lookup, setLookup] = useState(null);
  const currentLookup = lookup?.location === location ? lookup : null;
  const menuItems = currentLookup?.items ?? [];
  const categoryOptions = currentLookup?.categories ?? [];
  const isLoadingItems = !!location && !currentLookup;

  // Fetch menu items when location_id changes
  useEffect(() => {
    setLookup(null);
    if (!location) {
      return;
    }

    let active = true;
    apiFetch({ path: `/prg-sp/v1/menus/${encodeURIComponent(location)}` })
      .then((data) => {
        if (!active) {
          return;
        }
        const itemGroups = Array.isArray(data.items) ? data.items : Array.isArray(data) ? data : [];
        const categories = Array.isArray(data.categories)
          ? data.categories
          : itemGroups.map((group) => ({ id: group.text, text: group.text }));
        const formatted = itemGroups.map((group) => ({
          label: group.text,
          options: group.children.map((child) => ({
            label: child.text,
            value: child.id,
          })),
        }));

        // Add an empty default
        formatted.unshift({ label: __("Select an item...", "sp-menu"), value: "" });

        setLookup({
          location,
          items: formatted,
          menus: Array.isArray(data.menus) ? data.menus : [],
          categories: categories.map((category) => ({
            label: category.text,
            value: category.id,
          })),
        });
      })
      .catch(() => {
        if (active) {
          setLookup({ location, error: true });
        }
      });
    return () => { active = false; };
  }, [location]);

  const updateHighlightItem = (index, key, value) => {
    const newItems = [...highlighted_items];
    newItems[index] = { ...newItems[index], [key]: value };
    setAttributes({ highlighted_items: newItems });
  };

  const addHighlightItem = () => {
    setAttributes({
      highlighted_items: [...highlighted_items, { item_identifier: "", highlight_type: "special" }],
    });
  };

  const removeHighlightItem = (index) => {
    const newItems = [...highlighted_items];
    newItems.splice(index, 1);
    setAttributes({ highlighted_items: newItems });
  };

  const updateCategoryFilter = (value) => {
    setAttributes({ category_filter: Array.isArray(value) ? value : [value].filter(Boolean) });
  };

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title={__("API Settings", "sp-menu")} initialOpen={true}>
          <TextControl
            label={__("Location ID", "sp-menu")}
            value={location_id}
            onChange={(val) => setAttributes({ location_id: val })}
            help={__("Enter the SinglePlatform Location ID.", "sp-menu")}
          />
          <TextControl
            label={__("Menu Name", "sp-menu")}
            value={menu_name}
            disabled={enable_menu_groups}
            onChange={(val) => setAttributes({ menu_name: val })}
            help={enable_menu_groups
              ? __("Menu Groups uses all menus from the location.", "sp-menu")
              : __("Exact menu name (optional).", "sp-menu")}
          />
          <SelectControl
            label={__("Category Filter", "sp-menu")}
            value={category_filter}
            multiple
            options={categoryOptions}
            onChange={updateCategoryFilter}
            help={__("Leave empty to show all categories.", "sp-menu")}
          />
        </PanelBody>

        <PanelBody title={__("Menu Groups", "sp-menu")} initialOpen={false}>
          <ToggleControl
            label={__("Enable Menu Groups", "sp-menu")}
            checked={enable_menu_groups}
            onChange={(value) => setAttributes({ enable_menu_groups: value })}
            help={__("Groups and standalone menus appear as top-level tabs.", "sp-menu")}
          />
          {enable_menu_groups && (
            <MenuGroups
              config={menu_groups}
              location={location}
              catalog={currentLookup?.menus ?? []}
              loading={isLoadingItems}
              error={!!currentLookup?.error}
              onChange={(config) => setAttributes({ menu_groups: config })}
            />
          )}
        </PanelBody>

        <PanelBody title={__("Highlighted Items", "sp-menu")} initialOpen={false}>
          <p className="description">{__("Add menu items to feature as a 'Special' or 'New' item.", "sp-menu")}</p>

          {isLoadingItems && <Spinner />}

          {!location_id && <p style={{ fontStyle: "italic" }}>{__("Please enter a Location ID first.", "sp-menu")}</p>}

          {highlighted_items.map((item, index) => (
            <div
              key={index}
              style={{ padding: "10px", border: "1px solid #ccc", marginBottom: "10px", borderRadius: "4px" }}>
              <SelectControl
                label={__("Item", "sp-menu")}
                value={item.item_identifier}
                options={menuItems}
                onChange={(val) => updateHighlightItem(index, "item_identifier", val)}
              />
              <SelectControl
                label={__("Type", "sp-menu")}
                value={item.highlight_type}
                options={[
                  { label: "Special", value: "special" },
                  { label: "Featured", value: "featured" },
                  { label: "New", value: "new" },
                ]}
                onChange={(val) => updateHighlightItem(index, "highlight_type", val)}
              />
              <Button isDestructive variant="link" onClick={() => removeHighlightItem(index)}>
                {__("Remove Item", "sp-menu")}
              </Button>
            </div>
          ))}

          <Button variant="secondary" onClick={addHighlightItem}>
            {__("Add Highlighted Item", "sp-menu")}
          </Button>
        </PanelBody>

        <PanelBody title={__("Display Settings", "sp-menu")} initialOpen={false}>
          <ToggleControl
            label={__("Show Prices", "sp-menu")}
            checked={show_prices}
            onChange={(val) => setAttributes({ show_prices: val })}
          />
          <SelectControl
            label={__("Currency", "sp-menu")}
            value={currency}
            options={[
              { label: "USD", value: "USD" },
              { label: "EUR", value: "EUR" },
              { label: "GBP", value: "GBP" },
              { label: "JPY", value: "JPY" },
            ]}
            onChange={(val) => setAttributes({ currency: val })}
          />
          <SelectControl
            label={__("Layout", "sp-menu")}
            value={layout}
            disabled={enable_menu_groups}
            options={[
              { label: __("Accordion", "sp-menu"), value: "accordion" },
              { label: __("Tabs", "sp-menu"), value: "tabs" },
            ]}
            onChange={(val) => setAttributes({ layout: val })}
          />
          <SelectControl
            label={__("Category Display", "sp-menu")}
            value={category_display}
            options={[
              { label: __("Collapsible (Accordion)", "sp-menu"), value: "accordion" },
              { label: __("Always Expanded", "sp-menu"), value: "expanded" },
            ]}
            onChange={(val) => setAttributes({ category_display: val })}
          />
          {category_display === "accordion" && (
            <SelectControl
              label={__("Initial State", "sp-menu")}
              value={expand_behavior}
              options={[
                { label: __("Collapsed", "sp-menu"), value: "collapsed" },
                { label: __("Expanded", "sp-menu"), value: "expanded" },
              ]}
              onChange={(val) => setAttributes({ expand_behavior: val })}
            />
          )}
          <SelectControl
            label={__("Items per Row", "sp-menu")}
            value={item_columns}
            options={[
              { label: "1 column", value: "1" },
              { label: "2 columns", value: "2" },
            ]}
            onChange={(val) => setAttributes({ item_columns: val })}
          />
        </PanelBody>

        <PanelBody title={__("Details", "sp-menu")} initialOpen={false}>
          <SelectControl
            label={__("Nutrition Info", "sp-menu")}
            value={nutrition_visibility}
            options={[
              { label: __("Hide", "sp-menu"), value: "hide" },
              { label: __("Show", "sp-menu"), value: "show" },
            ]}
            onChange={(val) => setAttributes({ nutrition_visibility: val })}
          />
          <SelectControl
            label={__("Dietary Labels", "sp-menu")}
            value={labels_visibility}
            options={[
              { label: __("Show", "sp-menu"), value: "show" },
              { label: __("Hide", "sp-menu"), value: "hide" },
            ]}
            onChange={(val) => setAttributes({ labels_visibility: val })}
          />
        </PanelBody>

        <PanelBody title={__("Caching", "sp-menu")} initialOpen={false}>
          <RangeControl
            label={__("Cache TTL (seconds)", "sp-menu")}
            value={cache_ttl}
            onChange={(val) => setAttributes({ cache_ttl: val })}
            min={60}
            max={86400}
          />
        </PanelBody>
      </InspectorControls>

      <div onClick={handleMenuInteraction} onKeyDown={handleMenuInteraction}>
        <ServerSideRender block="prg/singleplatform-menu" attributes={attributes} httpMethod="POST" />
      </div>
    </div>
  );
}
