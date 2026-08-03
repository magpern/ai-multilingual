import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { LanguageOption } from '../types/view-models';

interface LanguageSelectProps {
	languages: LanguageOption[];
	value: string;
	onChange: (code: string) => void;
	disabled?: boolean;
}

export default function LanguageSelect( {
	languages,
	value,
	onChange,
	disabled = false,
}: LanguageSelectProps ) {
	const options = [
		{ label: __('Select a language', 'ai-multilingual'), value: '' },
		...languages.map( ( language ) => ( {
			label: language.native_name || language.name || language.code,
			value: language.code,
		} ) ),
	];

	return (
		<SelectControl
			label={ __( 'Language', 'ai-multilingual' ) }
			value={ value }
			options={ options }
			onChange={ onChange }
			disabled={ disabled }
		/>
	);
}
