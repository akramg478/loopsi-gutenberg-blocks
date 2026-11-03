import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	Button,
	CheckboxControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const ORDER_OPTIONS = [
	{ label: __( 'Descending', 'loopsi-blocks' ), value: 'DESC' },
	{ label: __( 'Ascending', 'loopsi-blocks' ), value: 'ASC' },
];

const ORDERBY_OPTIONS = [
	{ label: __( 'Date', 'loopsi-blocks' ), value: 'date' },
	{ label: __( 'Title', 'loopsi-blocks' ), value: 'title' },
	{ label: __( 'Modified', 'loopsi-blocks' ), value: 'modified' },
	{ label: __( 'Menu order', 'loopsi-blocks' ), value: 'menu_order' },
	{ label: __( 'Random', 'loopsi-blocks' ), value: 'rand' },
];

const PAGINATION_OPTIONS = [
	{ label: __( 'None', 'loopsi-blocks' ), value: 'none' },
	{ label: __( 'Pagination', 'loopsi-blocks' ), value: 'pagination' },
	{ label: __( 'Load More', 'loopsi-blocks' ), value: 'loadMore' },
	{ label: __( 'Infinite Scroll', 'loopsi-blocks' ), value: 'infiniteScroll' },
];

const STICKY_OPTIONS = [
	{ label: __( 'Inherit WordPress behavior', 'loopsi-blocks' ), value: 'inherit' },
	{ label: __( 'Sticky posts only', 'loopsi-blocks' ), value: 'only' },
	{ label: __( 'Exclude sticky posts', 'loopsi-blocks' ), value: 'exclude' },
];

const COLUMN_OPTIONS = [
	{ label: '1', value: '1' },
	{ label: '2', value: '2' },
	{ label: '3', value: '3' },
	{ label: '4', value: '4' },
	{ label: '5', value: '5' },
	{ label: '6', value: '6' },
];

function TaxonomyFilters( { postType, taxQuery, setAttributes } ) {
	const taxonomies = useSelect(
		( select ) => select( 'core' ).getTaxonomies( { type: postType, per_page: -1 } ) || [],
		[ postType ]
	);

	const visibleTaxonomies = taxonomies.filter(
		( taxonomy ) => taxonomy.visibility?.publicly_queryable || taxonomy.visibility?.public
	);

	if ( ! visibleTaxonomies.length ) {
		return (
			<p className="loopsi-panel-note">
				{ __( 'No public taxonomies are assigned to this post type.', 'loopsi-blocks' ) }
			</p>
		);
	}

	return visibleTaxonomies.map( ( taxonomy ) => (
		<TaxonomyCheckboxGroup
			key={ taxonomy.slug }
			taxonomy={ taxonomy }
			selectedIds={ taxQuery?.[ taxonomy.slug ] || [] }
			onChange={ ( termIds ) => {
				const next = { ...taxQuery, [ taxonomy.slug ]: termIds };
				if ( ! termIds.length ) {
					delete next[ taxonomy.slug ];
				}
				setAttributes( { taxQuery: next } );
			} }
		/>
	) );
}

function TaxonomyCheckboxGroup( { taxonomy, selectedIds, onChange } ) {
	const terms = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', taxonomy.slug, {
				per_page: 100,
				hide_empty: false,
			} ) || [],
		[ taxonomy.slug ]
	);

	if ( ! terms.length ) {
		return (
			<fieldset className="loopsi-taxonomy-group">
				<legend>{ taxonomy.name }</legend>
				<p className="loopsi-panel-note">
					{ __( 'No terms found for this taxonomy.', 'loopsi-blocks' ) }
				</p>
			</fieldset>
		);
	}

	const toggleTerm = ( termId, checked ) => {
		const nextIds = checked
			? [ ...selectedIds, termId ]
			: selectedIds.filter( ( id ) => id !== termId );
		onChange( nextIds );
	};

	return (
		<fieldset className="loopsi-taxonomy-group">
			<legend>{ taxonomy.name }</legend>
			{ terms.map( ( term ) => (
				<CheckboxControl
					key={ term.id }
					label={ term.name }
					checked={ selectedIds.includes( term.id ) }
					onChange={ ( checked ) => toggleTerm( term.id, checked ) }
				/>
			) ) }
		</fieldset>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		postsPerPage,
		postType,
		order,
		orderBy,
		showTitle,
		showThumbnail,
		showFeaturedImage,
		showExcerpt,
		showAuthor,
		showReadMore,
		readMoreText,
		layout,
		columns,
		search,
		cacheResults,
		cacheTTL,
		paginationType,
		stickyPosts,
		metaQuery = [],
		taxQuery = {},
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'loopsi-block-editor',
	} );

	const postTypes = useSelect(
		( select ) => select( 'core' ).getPostTypes( { per_page: -1 } ) || [],
		[]
	);

	const postTypeOptions = postTypes
		.filter( ( type ) => type.viewable && 'attachment' !== type.slug )
		.map( ( type ) => ( {
			label: type.name,
			value: type.slug,
		} ) );

	useEffect( () => {
		if ( ! postTypeOptions.length ) {
			return;
		}

		const isCurrentValid = postTypeOptions.some( ( option ) => option.value === postType );
		if ( ! isCurrentValid ) {
			setAttributes( { postType: postTypeOptions[0].value } );
		}
	}, [ postTypeOptions, postType, setAttributes ] );

	const addMetaRule = () => {
		setAttributes( {
			metaQuery: [
				...metaQuery,
				{ key: '', value: '', compare: '=', type: 'CHAR' },
			],
		} );
	};

	const updateMetaRule = ( index, field, value ) => {
		const updated = [ ...metaQuery ];
		updated[ index ] = {
			...updated[ index ],
			[ field ]: value,
		};
		setAttributes( { metaQuery: updated } );
	};

	const removeMetaRule = ( index ) => {
		const updated = [ ...metaQuery ];
		updated.splice( index, 1 );
		setAttributes( { metaQuery: updated } );
	};

	const thumbnailEnabled = showThumbnail ?? showFeaturedImage ?? true;

	const setThumbnail = ( value ) => {
		setAttributes( {
			showThumbnail: value,
			showFeaturedImage: value,
		} );
	};

	const previewKey = [
		postType,
		columns ?? 3,
		layout,
		postsPerPage,
		paginationType,
		order,
		orderBy,
		search,
		JSON.stringify( taxQuery ),
	].join( '|' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Query Settings', 'loopsi-blocks' ) } initialOpen={ true }>
					<RangeControl
						label={ __( 'Posts Per Page', 'loopsi-blocks' ) }
						value={ postsPerPage }
						onChange={ ( val ) => setAttributes( { postsPerPage: val } ) }
						min={ 1 }
						max={ 50 }
					/>

					<SelectControl
						label={ __( 'Post Type', 'loopsi-blocks' ) }
						value={ postType }
						options={
							postTypeOptions.length
								? postTypeOptions
								: [ { label: __( 'Posts', 'loopsi-blocks' ), value: 'post' } ]
						}
						onChange={ ( val ) => setAttributes( { postType: val, taxQuery: {} } ) }
					/>

					<SelectControl
						label={ __( 'Order', 'loopsi-blocks' ) }
						value={ order }
						options={ ORDER_OPTIONS }
						onChange={ ( val ) => setAttributes( { order: val } ) }
					/>

					<SelectControl
						label={ __( 'Order By', 'loopsi-blocks' ) }
						value={ orderBy }
						options={ ORDERBY_OPTIONS }
						onChange={ ( val ) => setAttributes( { orderBy: val } ) }
					/>

					<SelectControl
						label={ __( 'Sticky Posts', 'loopsi-blocks' ) }
						value={ stickyPosts }
						options={ STICKY_OPTIONS }
						onChange={ ( val ) => setAttributes( { stickyPosts: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Filters', 'loopsi-blocks' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Search', 'loopsi-blocks' ) }
						value={ search }
						onChange={ ( val ) => setAttributes( { search: val } ) }
					/>

					<SelectControl
						label={ __( 'Pagination Type', 'loopsi-blocks' ) }
						value={ paginationType }
						options={ PAGINATION_OPTIONS }
						onChange={ ( val ) => setAttributes( { paginationType: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Taxonomy Filters', 'loopsi-blocks' ) } initialOpen={ false }>
					<p className="loopsi-panel-note">
						{ __(
							'Select terms from taxonomies assigned to the chosen post type. Posts matching any checked term within each taxonomy will be included.',
							'loopsi-blocks'
						) }
					</p>
					<TaxonomyFilters
						postType={ postType }
						taxQuery={ taxQuery }
						setAttributes={ setAttributes }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Meta Query Builder', 'loopsi-blocks' ) } initialOpen={ false }>
					<Button variant="primary" onClick={ addMetaRule }>
						{ __( 'Add Condition', 'loopsi-blocks' ) }
					</Button>

					{ metaQuery.map( ( rule, index ) => (
						<div key={ index } className="loopsi-meta-rule">
							<TextControl
								label={ __( 'Meta Key', 'loopsi-blocks' ) }
								value={ rule.key }
								onChange={ ( val ) => updateMetaRule( index, 'key', val ) }
							/>

							<TextControl
								label={ __( 'Value', 'loopsi-blocks' ) }
								value={ rule.value }
								onChange={ ( val ) => updateMetaRule( index, 'value', val ) }
							/>

							<SelectControl
								label={ __( 'Compare', 'loopsi-blocks' ) }
								value={ rule.compare }
								options={ [
									{ label: '=', value: '=' },
									{ label: '!=', value: '!=' },
									{ label: '>', value: '>' },
									{ label: '<', value: '<' },
									{ label: 'LIKE', value: 'LIKE' },
									{ label: 'EXISTS', value: 'EXISTS' },
									{ label: 'NOT EXISTS', value: 'NOT EXISTS' },
								] }
								onChange={ ( val ) => updateMetaRule( index, 'compare', val ) }
							/>

							<SelectControl
								label={ __( 'Type', 'loopsi-blocks' ) }
								value={ rule.type }
								options={ [
									{ label: 'CHAR', value: 'CHAR' },
									{ label: 'NUMERIC', value: 'NUMERIC' },
									{ label: 'DATE', value: 'DATE' },
								] }
								onChange={ ( val ) => updateMetaRule( index, 'type', val ) }
							/>

							<Button isDestructive onClick={ () => removeMetaRule( index ) }>
								{ __( 'Remove', 'loopsi-blocks' ) }
							</Button>
						</div>
					) ) }
				</PanelBody>

				<PanelBody title={ __( 'Display Settings', 'loopsi-blocks' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Layout', 'loopsi-blocks' ) }
						value={ layout }
						options={ [
							{ label: __( 'Grid', 'loopsi-blocks' ), value: 'grid' },
							{ label: __( 'List', 'loopsi-blocks' ), value: 'list' },
						] }
						onChange={ ( val ) => setAttributes( { layout: val } ) }
					/>

					{ layout === 'grid' && (
						<SelectControl
							label={ __( 'Columns', 'loopsi-blocks' ) }
							value={ String( columns ?? 3 ) }
							options={ COLUMN_OPTIONS }
							onChange={ ( val ) => setAttributes( { columns: parseInt( val, 10 ) } ) }
						/>
					) }

					<ToggleControl
						label={ __( 'Show Title', 'loopsi-blocks' ) }
						checked={ showTitle !== false }
						onChange={ ( val ) => setAttributes( { showTitle: val } ) }
					/>

					<ToggleControl
						label={ __( 'Show Thumbnail', 'loopsi-blocks' ) }
						checked={ !! thumbnailEnabled }
						onChange={ setThumbnail }
					/>

					<ToggleControl
						label={ __( 'Show Excerpt', 'loopsi-blocks' ) }
						checked={ showExcerpt !== false }
						onChange={ ( val ) => setAttributes( { showExcerpt: val } ) }
					/>

					<ToggleControl
						label={ __( 'Show Author', 'loopsi-blocks' ) }
						checked={ !! showAuthor }
						onChange={ ( val ) => setAttributes( { showAuthor: val } ) }
					/>

					<ToggleControl
						label={ __( 'Show Read More Button', 'loopsi-blocks' ) }
						checked={ showReadMore !== false }
						onChange={ ( val ) => setAttributes( { showReadMore: val } ) }
					/>

					{ showReadMore !== false && (
						<TextControl
							label={ __( 'Read More Text', 'loopsi-blocks' ) }
							value={ readMoreText || __( 'Read more', 'loopsi-blocks' ) }
							onChange={ ( val ) => setAttributes( { readMoreText: val } ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Performance', 'loopsi-blocks' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Enable Cache', 'loopsi-blocks' ) }
						checked={ !! cacheResults }
						onChange={ ( val ) => setAttributes( { cacheResults: val } ) }
					/>

					<RangeControl
						label={ __( 'Cache TTL (seconds)', 'loopsi-blocks' ) }
						value={ cacheTTL }
						min={ 60 }
						max={ 3600 }
						step={ 60 }
						onChange={ ( val ) => setAttributes( { cacheTTL: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					key={ previewKey }
					block="loopsi/posts-query"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="loopsi-block-preview-loading">
							<Spinner />
						</div>
					) }
					ErrorResponsePlaceholder={ () => (
						<p className="loopsi-empty">{ __( 'Unable to render preview.', 'loopsi-blocks' ) }</p>
					) }
					EmptyResponsePlaceholder={ () => (
						<p className="loopsi-empty">{ __( 'No posts found.', 'loopsi-blocks' ) }</p>
					) }
				/>
			</div>
		</>
	);
}
