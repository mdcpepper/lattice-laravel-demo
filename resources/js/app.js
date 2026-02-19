import "./bootstrap";

const SIDEBAR_ALIGNMENT_TOLERANCE = 2;

const syncSidebarState = (layout) => {
    const primaryContent = layout.firstElementChild;
    const cartSidebar = layout.querySelector(".cart-sidebar");

    if (
        !(primaryContent instanceof HTMLElement) ||
        !(cartSidebar instanceof HTMLElement)
    ) {
        layout.classList.remove("is-sidebar");

        return;
    }

    const contentBounds = primaryContent.getBoundingClientRect();
    const sidebarBounds = cartSidebar.getBoundingClientRect();

    const isOnSameRow =
        Math.abs(contentBounds.top - sidebarBounds.top) <=
        SIDEBAR_ALIGNMENT_TOLERANCE;

    const isOnRightSide =
        sidebarBounds.left >= contentBounds.left + SIDEBAR_ALIGNMENT_TOLERANCE;

    layout.classList.toggle("is-sidebar", isOnSameRow && isOnRightSide);
};

const initializeSidebarStateTracking = () => {
    const sidebarLayouts = document.querySelectorAll(".with-sidebar");

    for (const layout of sidebarLayouts) {
        if (!(layout instanceof HTMLElement)) {
            continue;
        }

        const syncLayoutState = () => {
            syncSidebarState(layout);
        };

        syncLayoutState();

        if (typeof ResizeObserver !== "undefined") {
            const observer = new ResizeObserver(() => {
                window.requestAnimationFrame(syncLayoutState);
            });

            observer.observe(layout);

            const primaryContent = layout.firstElementChild;
            const cartSidebar = layout.querySelector(".cart-sidebar");

            if (primaryContent instanceof HTMLElement) {
                observer.observe(primaryContent);
            }

            if (cartSidebar instanceof HTMLElement) {
                observer.observe(cartSidebar);
            }
        } else {
            window.addEventListener("resize", syncLayoutState, {
                passive: true,
            });
        }
    }
};

if (document.readyState === "loading") {
    document.addEventListener(
        "DOMContentLoaded",
        initializeSidebarStateTracking,
    );
} else {
    initializeSidebarStateTracking();
}
