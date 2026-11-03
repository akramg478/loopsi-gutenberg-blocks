( function () {
	'use strict';

	function getAjaxConfig() {
		if ( typeof loopsiAjax === 'undefined' ) {
			return null;
		}

		return loopsiAjax;
	}

	/**
	 * Find the grid associated with a pagination control.
	 *
	 * @param {HTMLElement} control Pagination button or wrapper.
	 * @return {HTMLElement|null}
	 */
	function findGrid( control ) {
		const wrap = control.closest( '.loopsi-block-wrap' );

		if ( wrap ) {
			const grid = wrap.querySelector( '.loopsi-post-grid' );

			if ( grid instanceof HTMLElement ) {
				return grid;
			}
		}

		const wrapper = control.closest( '.loopsi-pagination' );
		const sibling = wrapper ? wrapper.previousElementSibling : null;

		return sibling instanceof HTMLElement && sibling.classList.contains( 'loopsi-post-grid' )
			? sibling
			: null;
	}

	/**
	 * @param {HTMLElement} sentinel Infinite scroll sentinel element.
	 * @return {HTMLElement|null}
	 */
	function getOrCreateInfiniteLoader( sentinel ) {
		const paginationWrap = sentinel.closest( '.loopsi-pagination' );

		if ( ! ( paginationWrap instanceof HTMLElement ) ) {
			return null;
		}

		let loader = paginationWrap.querySelector( '.loopsi-loading-status' );

		if ( loader instanceof HTMLElement ) {
			return loader;
		}

		loader = document.createElement( 'div' );
		loader.className = 'loopsi-loading-status';
		loader.setAttribute( 'aria-live', 'polite' );
		loader.setAttribute( 'role', 'status' );
		loader.innerHTML =
			'<span class="loopsi-loading-spinner" aria-hidden="true"></span>' +
			'<span class="loopsi-loading-text"></span>';

		paginationWrap.insertBefore( loader, sentinel );

		return loader;
	}

	/**
	 * @param {HTMLElement|null} loader Loader element.
	 * @param {boolean}          visible Whether loader is visible.
	 */
	function setLoaderVisible( loader, visible ) {
		if ( ! ( loader instanceof HTMLElement ) ) {
			return;
		}

		const ajaxConfig = getAjaxConfig();
		const textEl = loader.querySelector( '.loopsi-loading-text' );

		if ( textEl ) {
			textEl.textContent = ajaxConfig?.i18n?.loading || 'Loading…';
		}

		loader.classList.toggle( 'is-visible', visible );
		loader.toggleAttribute( 'aria-busy', visible );
	}

	/**
	 * Parse query payload stored on the grid element.
	 *
	 * @param {HTMLElement} grid Grid wrapper.
	 * @return {Object|null}
	 */
	function getQueryPayload( grid ) {
		const raw = grid.getAttribute( 'data-loopsi-query' );

		if ( ! raw ) {
			return null;
		}

		try {
			return JSON.parse( raw );
		} catch ( error ) {
			return null;
		}
	}

	/**
	 * Fetch a page of posts via admin-ajax.
	 *
	 * @param {HTMLElement} grid Grid wrapper.
	 * @param {number}      page Target page.
	 * @return {Promise<{html: string, has_more: boolean}>}
	 */
	async function fetchPage( grid, page ) {
		const ajaxConfig = getAjaxConfig();
		const query = getQueryPayload( grid );

		if ( ! ajaxConfig || ! query ) {
			return { html: '', has_more: false };
		}

		const formData = new FormData();
		formData.append( 'action', 'loopsi_load_more' );
		formData.append( 'nonce', ajaxConfig.nonce );
		formData.append( 'paged', String( page ) );
		formData.append( 'query', JSON.stringify( query ) );

		const response = await fetch( ajaxConfig.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} );

		const payload = await response.json();

		if ( ! payload.success || ! payload.data ) {
			return { html: '', has_more: false };
		}

		return {
			html: payload.data.html || '',
			has_more: !! payload.data.has_more,
		};
	}

	/**
	 * @param {HTMLElement} wrapper Pagination wrapper.
	 * @param {number}      page    Active page.
	 */
	function setActivePaginationPage( wrapper, page ) {
		wrapper.querySelectorAll( '.loopsi-page' ).forEach( function ( control ) {
			if ( ! ( control instanceof HTMLButtonElement ) ) {
				return;
			}

			const controlPage = parseInt( control.getAttribute( 'data-page' ) || '1', 10 );
			const isActive = controlPage === page;

			control.classList.toggle( 'is-active', isActive );
			control.disabled = isActive;

			if ( isActive ) {
				control.setAttribute( 'aria-current', 'page' );
			} else {
				control.removeAttribute( 'aria-current' );
			}
		} );
	}

	/**
	 * Initialize infinite scroll observers for any uninitialized grids.
	 */
	function initInfiniteScroll() {
		document.querySelectorAll( '.loopsi-post-grid' ).forEach( function ( grid ) {
			if ( ! ( grid instanceof HTMLElement ) ) {
				return;
			}

			const paginationType = ( grid.getAttribute( 'data-pagination-type' ) || '' ).toLowerCase();

			if ( paginationType !== 'infinitescroll' ) {
				return;
			}

			if ( grid.dataset.loopsiInfiniteReady === '1' ) {
				return;
			}

			const maxPages = parseInt( grid.getAttribute( 'data-max-pages' ) || '1', 10 );
			let page = parseInt( grid.getAttribute( 'data-page' ) || '1', 10 );

			if ( page >= maxPages ) {
				return;
			}

			const wrap = grid.closest( '.loopsi-block-wrap' );
			const sentinel = wrap
				? wrap.querySelector( '.loopsi-infinite-sentinel' )
				: grid.parentElement
					? grid.parentElement.querySelector( '.loopsi-infinite-sentinel' )
					: null;

			if ( ! ( sentinel instanceof HTMLElement ) ) {
				return;
			}

			grid.dataset.loopsiInfiniteReady = '1';

			let loading = false;
			let hasMore = page < maxPages;
			const loader = getOrCreateInfiniteLoader( sentinel );

			const observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( ! entry.isIntersecting || loading || ! hasMore ) {
							return;
						}

						loading = true;
						page += 1;
						setLoaderVisible( loader, true );

						fetchPage( grid, page )
							.then( function ( result ) {
								if ( result.html ) {
									grid.insertAdjacentHTML( 'beforeend', result.html );
									grid.setAttribute( 'data-page', String( page ) );
									hasMore = result.has_more;
								} else {
									hasMore = false;
								}
							} )
							.catch( function () {
								hasMore = false;
							} )
							.finally( function () {
								loading = false;
								setLoaderVisible( loader, false );
							} );
					} );
				},
				{
					root: null,
					rootMargin: '200px',
					threshold: 0,
				}
			);

			observer.observe( sentinel );
		} );
	}

	document.addEventListener( 'click', async function ( event ) {
		const target = event.target;

		if ( ! ( target instanceof HTMLElement ) ) {
			return;
		}

		const pageButton = target.closest( '.loopsi-page' );

		if ( pageButton instanceof HTMLButtonElement && ! pageButton.classList.contains( 'is-active' ) ) {
			event.preventDefault();

			const wrapper = pageButton.closest( '.loopsi-pagination' );

			if ( ! ( wrapper instanceof HTMLElement ) ) {
				return;
			}

			const grid = findGrid( pageButton );

			if ( ! grid ) {
				return;
			}

			const page = parseInt( pageButton.getAttribute( 'data-page' ) || '1', 10 );
			const buttons = wrapper.querySelectorAll( '.loopsi-page' );

			buttons.forEach( function ( button ) {
				if ( button instanceof HTMLButtonElement ) {
					button.disabled = true;
				}
			} );

			try {
				const result = await fetchPage( grid, page );

				if ( result.html ) {
					grid.innerHTML = result.html;
					grid.setAttribute( 'data-page', String( page ) );
					setActivePaginationPage( wrapper, page );
					grid.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				}
			} finally {
				setActivePaginationPage( wrapper, page );
			}

			return;
		}

		const loadMoreBtn = target.closest( '.loopsi-load-more' );

		if ( ! ( loadMoreBtn instanceof HTMLButtonElement ) ) {
			return;
		}

		event.preventDefault();

		const wrapper = loadMoreBtn.closest( '.loopsi-pagination' );

		if ( ! ( wrapper instanceof HTMLElement ) ) {
			return;
		}

		const grid = findGrid( loadMoreBtn );

		if ( ! grid ) {
			return;
		}

		const ajaxConfig = getAjaxConfig();

		if ( ! ajaxConfig ) {
			return;
		}

		let page = parseInt( grid.getAttribute( 'data-page' ) || '1', 10 );
		page += 1;

		loadMoreBtn.textContent = ajaxConfig.i18n.loading;
		loadMoreBtn.disabled = true;

		try {
			const result = await fetchPage( grid, page );

			if ( result.html ) {
				grid.insertAdjacentHTML( 'beforeend', result.html );
				grid.setAttribute( 'data-page', String( page ) );
			}

			if ( ! result.has_more ) {
				wrapper.remove();
			} else {
				loadMoreBtn.textContent = ajaxConfig.i18n.loadMore;
				loadMoreBtn.disabled = false;
			}
		} catch ( error ) {
			loadMoreBtn.textContent = ajaxConfig.i18n.loadMore;
			loadMoreBtn.disabled = false;
		}
	} );

	function boot() {
		initInfiniteScroll();

		if ( typeof MutationObserver !== 'undefined' && document.body ) {
			const observer = new MutationObserver( function () {
				initInfiniteScroll();
			} );

			observer.observe( document.body, {
				childList: true,
				subtree: true,
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
