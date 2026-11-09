function activate(tabs, selected, root) {
  for (const tab of tabs) {
    const active = tab === selected;
    tab.setAttribute("aria-selected", String(active));
    tab.tabIndex = active ? 0 : -1;
    const panel = root.ownerDocument.getElementById(tab.getAttribute("aria-controls"));
    if (panel && root.contains(panel)) {
      panel.hidden = !active;
    }
  }
  selected.focus();
}

export function handleMenuInteraction(event) {
  const control = event.target.closest?.(".sp-menu__tab, .sp-menu__toggle");
  const root = control?.closest(".sp-menu");
  if (!root || event.defaultPrevented) {
    return;
  }

  if (control.classList.contains("sp-menu__toggle")) {
    if (event.type !== "click") {
      return;
    }
    const panel = root.ownerDocument.getElementById(control.getAttribute("aria-controls"));
    if (panel && root.contains(panel)) {
      const expanded = control.getAttribute("aria-expanded") !== "true";
      control.setAttribute("aria-expanded", String(expanded));
      panel.hidden = !expanded;
    }
    return;
  }

  const list = control.parentElement;
  if (list.getAttribute("role") !== "tablist") {
    return;
  }
  const tabs = Array.from(list.children).filter((tab) => tab.getAttribute("role") === "tab");
  const index = tabs.indexOf(control);
  const legacy = !root.classList.contains("sp-menu--grouped");
  let nextIndex = index;

  if (event.type === "keydown") {
    if (event.key === "ArrowRight" || (legacy && event.key === "ArrowDown")) {
      nextIndex = (index + 1) % tabs.length;
    } else if (event.key === "ArrowLeft" || (legacy && event.key === "ArrowUp")) {
      nextIndex = (index - 1 + tabs.length) % tabs.length;
    } else if (event.key === "Home") {
      nextIndex = 0;
    } else if (event.key === "End") {
      nextIndex = tabs.length - 1;
    } else {
      return;
    }
  }

  event.preventDefault();
  activate(tabs, tabs[nextIndex], root);
}
