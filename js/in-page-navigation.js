(() => {
  'use strict';

  const NAV_SELECTOR = '[data-in-page-nav]';
  const DEFAULT_HEADING_SELECTOR = 'h2, h3';

  const slugify = (value) => value
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'section';

  const assignHeadingIds = (headings) => {
    const headingSet = new Set(headings);
    const usedIds = new Set(
      Array.from(document.querySelectorAll('[id]'))
        .filter((element) => !headingSet.has(element))
        .map((element) => element.id)
    );

    headings.forEach((heading, index) => {
      const baseId = heading.id || slugify(heading.textContent.trim()) || `section-${index + 1}`;
      let uniqueId = baseId;
      let suffix = 2;

      while (usedIds.has(uniqueId)) {
        uniqueId = `${baseId}-${suffix}`;
        suffix += 1;
      }

      heading.id = uniqueId;
      usedIds.add(uniqueId);
    });
  };

  const headingLevel = (heading) => Number.parseInt(heading.tagName.slice(1), 10) || 2;

  const currentHashId = () => {
    const hash = window.location.hash.slice(1);

    try {
      return decodeURIComponent(hash);
    } catch {
      return hash;
    }
  };

  const createNavItem = (heading) => {
    const item = document.createElement('li');
    const link = document.createElement('a');
    const level = headingLevel(heading);

    item.className = `in-page-nav-item in-page-nav-item-level-${level}`;
    link.className = 'in-page-nav-link';
    link.href = `#${heading.id}`;
    link.textContent = heading.textContent.trim();
    link.dataset.inPageNavHeading = heading.id;

    item.append(link);
    return item;
  };

  const buildNavigationList = (headings) => {
    const rootList = document.createElement('ol');
    const baseLevel = Math.min(...headings.map(headingLevel));
    const stack = [{ level: baseLevel, list: rootList }];

    rootList.className = 'in-page-nav-list';

    headings.forEach((heading) => {
      const level = headingLevel(heading);

      while (stack.length > 1 && level < stack[stack.length - 1].level) {
        stack.pop();
      }

      if (level > stack[stack.length - 1].level) {
        const parentItem = stack[stack.length - 1].list.lastElementChild;

        if (parentItem) {
          const nestedList = document.createElement('ol');
          nestedList.className = 'in-page-nav-sublist';
          parentItem.append(nestedList);
          stack.push({ level, list: nestedList });
        }
      }

      while (stack.length > 1 && level < stack[stack.length - 1].level) {
        stack.pop();
      }

      stack[stack.length - 1].list.append(createNavItem(heading));
    });

    return rootList;
  };

  const initializeNavigation = (nav) => {
    const targetId = nav.dataset.inPageNavTarget;
    const target = targetId ? document.getElementById(targetId) : null;

    if (!target) {
      nav.hidden = true;
      return;
    }

    const selector = nav.dataset.inPageNavSelector || DEFAULT_HEADING_SELECTOR;
    const headings = Array.from(target.querySelectorAll(selector))
      .filter((heading) => !heading.matches('[data-in-page-nav-ignore]'))
      .filter((heading) => heading.textContent.trim() !== '');

    if (headings.length === 0) {
      nav.hidden = true;
      return;
    }

    assignHeadingIds(headings);
    nav.replaceChildren(buildNavigationList(headings));
    nav.dataset.inPageNavReady = 'true';

    const links = Array.from(nav.querySelectorAll('.in-page-nav-link'));
    const linksByHeadingId = new Map(
      links.map((link) => [link.dataset.inPageNavHeading, link])
    );
    const collapsible = nav.closest('details');
    const collapseWidth = Number.parseInt(nav.dataset.inPageNavCollapseWidth || '', 10);
    let frameRequested = false;

    const setActiveHeading = (headingId) => {
      links.forEach((link) => {
        const isActive = link.dataset.inPageNavHeading === headingId;
        link.classList.toggle('is-active', isActive);

        if (isActive) {
          link.setAttribute('aria-current', 'location');
        } else {
          link.removeAttribute('aria-current');
        }
      });
    };

    const updateActiveHeading = () => {
      frameRequested = false;
      const threshold = Math.min(180, window.innerHeight * 0.25);
      let activeHeading = headings[0];

      headings.forEach((heading) => {
        if (heading.getBoundingClientRect().top <= threshold) {
          activeHeading = heading;
        }
      });

      const pageBottom = window.scrollY + window.innerHeight;
      const documentBottom = document.documentElement.scrollHeight - 2;

      if (pageBottom >= documentBottom) {
        activeHeading = headings[headings.length - 1];
      }

      setActiveHeading(activeHeading.id);
    };

    const scheduleActiveHeadingUpdate = () => {
      if (frameRequested) return;

      frameRequested = true;
      window.requestAnimationFrame(updateActiveHeading);
    };

    links.forEach((link) => {
      link.addEventListener('click', () => {
        const headingId = link.dataset.inPageNavHeading;

        if (linksByHeadingId.has(headingId)) {
          setActiveHeading(headingId);
        }

        if (
          collapsible
          && Number.isFinite(collapseWidth)
          && window.matchMedia(`(max-width: ${collapseWidth}px)`).matches
        ) {
          collapsible.open = false;
        }
      });
    });

    window.addEventListener('scroll', scheduleActiveHeadingUpdate, { passive: true });
    window.addEventListener('resize', scheduleActiveHeadingUpdate);
    window.addEventListener('hashchange', () => {
      const hashId = currentHashId();
      if (linksByHeadingId.has(hashId)) setActiveHeading(hashId);
    });

    const initialHashId = currentHashId();
    setActiveHeading(linksByHeadingId.has(initialHashId) ? initialHashId : headings[0].id);
  };

  const initializeInPageNavigation = () => {
    document.querySelectorAll(NAV_SELECTOR).forEach(initializeNavigation);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeInPageNavigation, { once: true });
  } else {
    initializeInPageNavigation();
  }
})();
