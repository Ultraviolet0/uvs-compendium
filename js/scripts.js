(() => {
  const initDiabloCompendium = () => {
    const sidebar = document.querySelector('.site-sidebar');
    const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
    const menus = Array.from(document.querySelectorAll('.nav-menu'));
    const mobileNavQuery = window.matchMedia('(max-width: 896px)');

    const closeMobileNav = () => {
      if (!sidebar || !mobileNavToggle) return;

      sidebar.classList.remove('is-mobile-nav-open');
      mobileNavToggle.setAttribute('aria-expanded', 'false');
    };

    const toggleMobileNav = () => {
      if (!sidebar || !mobileNavToggle) return;

      const isOpen = sidebar.classList.toggle('is-mobile-nav-open');
      mobileNavToggle.setAttribute('aria-expanded', String(isOpen));
    };

    const closeMenu = (menu) => {
      if (!menu) return;

      const button = menu.querySelector('.nav-menu-toggle');
      menu.classList.remove('is-open');
      button?.setAttribute('aria-expanded', 'false');
    };

    const openMenu = (menu) => {
      if (!menu) return;

      const button = menu.querySelector('.nav-menu-toggle');
      menu.classList.add('is-open');
      button?.setAttribute('aria-expanded', 'true');
    };

    const toggleMenu = (menu) => {
      const isOpen = menu.classList.contains('is-open');

      menus.forEach((otherMenu) => {
        if (otherMenu !== menu) closeMenu(otherMenu);
      });

      if (isOpen) {
        closeMenu(menu);
      } else {
        openMenu(menu);
      }
    };

    const normalizePath = (href) => {
      try {
        const url = new URL(href, window.location.href);
        let path = url.pathname.replace(/\/index\.(html|php)$/, '/');

        if (path.endsWith('/')) {
          path += 'index.php';
        }

        return path;
      } catch {
        return '';
      }
    };

    mobileNavToggle?.addEventListener('click', (event) => {
      event.preventDefault();
      toggleMobileNav();
    });

    document.addEventListener('click', (event) => {
      if (!sidebar?.classList.contains('is-mobile-nav-open')) return;
      if (!mobileNavQuery.matches) return;
      if (sidebar.contains(event.target)) return;

      closeMobileNav();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeMobileNav();
      }
    });

    const handleMobileNavQueryChange = (event) => {
      if (!event.matches) {
        closeMobileNav();
      }
    };

    if (typeof mobileNavQuery.addEventListener === 'function') {
      mobileNavQuery.addEventListener('change', handleMobileNavQueryChange);
    } else if (typeof mobileNavQuery.addListener === 'function') {
      mobileNavQuery.addListener(handleMobileNavQueryChange);
    }

    menus.forEach((menu) => {
      const button = menu.querySelector('.nav-menu-toggle');
      if (!button) return;

      button.addEventListener('click', (event) => {
        event.preventDefault();
        toggleMenu(menu);
      });
    });

    const currentPath = normalizePath(window.location.href);
    const navLinks = Array.from(document.querySelectorAll('.site-nav a[href]'));

    navLinks.forEach((link) => {
      const href = link.getAttribute('href') || '';

      if (href === '#' || href.startsWith('http')) {
        return;
      }

      const linkPath = normalizePath(link.href);
      const isCurrentPage = linkPath === currentPath && !link.hash;
      const isCurrentHash = linkPath === currentPath && link.hash && link.hash === window.location.hash;

      if (isCurrentPage || isCurrentHash) {
        link.classList.add('is-active');
        link.setAttribute('aria-current', isCurrentHash ? 'location' : 'page');
        openMenu(link.closest('.nav-menu'));
      }

      link.addEventListener('click', () => {
        if (mobileNavQuery.matches) {
          closeMobileNav();
        }
      });
    });

    const year = document.getElementById('current-year');
    if (year) {
      year.textContent = new Date().getFullYear();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDiabloCompendium, { once: true });
  } else {
    initDiabloCompendium();
  }
})();
