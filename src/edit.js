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

export default function Edit({ attributes, setAttributes }) {
  const {
    location_id,
    menu_name,
    highlighted_items,
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

  const [menuItems, setMenuItems] = useState([]);
  const [isLoadingItems, setIsLoadingItems] = useState(false);

  // Fetch menu items when location_id changes
  useEffect(() => {
    if (!location_id || location_id.length < 3) {
      setMenuItems([]);
      return;
    }

    setIsLoadingItems(true);

    apiFetch({ path: `/prg-sp/v1/menus/${encodeURIComponent(location_id)}` })
      .then((data) => {
        // The API returns grouped data { text: "Group", children: [...] }
        // We need to flatten this for a standard SelectControl or map it properly
        // SelectControl supports optgroups if formatted as [ { label: 'Group', options: [...] } ]
        const formatted = data.map((group) => ({
          label: group.text,
          options: group.children.map((child) => ({
            label: child.text,
            value: child.id,
          })),
        }));

        // Add an empty default
        formatted.unshift({ label: __("Select an item...", "sp-menu"), value: "" });

        setMenuItems(formatted);
      })
      .catch(() => {
        setMenuItems([]);
      })
      .finally(() => {
        setIsLoadingItems(false);
      });
  }, [location_id]);

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
            onChange={(val) => setAttributes({ menu_name: val })}
            help={__("Exact menu name (optional).", "sp-menu")}
          />
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

      <ServerSideRender block="prg/singleplatform-menu" attributes={attributes} />
    </div>
  );
}
