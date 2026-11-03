import { registerBlockType } from '@wordpress/blocks';

import metadata from './blocks/posts-query/block.json';
import Edit from './blocks/posts-query/edit';
import save from './blocks/posts-query/save';

import './blocks/posts-query';

registerBlockType( metadata, {
	edit: Edit,
	save,
} );