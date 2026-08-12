import { useCallback, useRef, useState } from '@wordpress/element';

import { canOpenConfirmDialog } from '../utils/dirty-leave-admission';

import ConfirmDialog from '../components/ConfirmDialog';

export interface ConfirmRequest {
	title: string;
	message: string;
	confirmLabel?: string;
	cancelLabel?: string;
	isDestructive?: boolean;
}

interface PendingConfirm extends ConfirmRequest {
	resolve: ( confirmed: boolean ) => void;
}

/**
 * Async ConfirmDialog admission helper (OTL.6 A1).
 *
 * Returns a Promise that resolves true only after explicit Confirm.
 * Concurrent requests while a dialog is open resolve false (no double-nav).
 */
export function useConfirmDialog(): {
	requestConfirm: ( request: ConfirmRequest ) => Promise< boolean >;
	confirmDialog: ReturnType< typeof ConfirmDialog > | null;
} {
	const [ pending, setPending ] = useState< PendingConfirm | null >( null );
	const pendingRef = useRef< PendingConfirm | null >( null );

	const requestConfirm = useCallback(
		( request: ConfirmRequest ): Promise< boolean > => {
			if ( ! canOpenConfirmDialog( Boolean( pendingRef.current ) ) ) {
				return Promise.resolve( false );
			}
			return new Promise< boolean >( ( resolve ) => {
				const next: PendingConfirm = {
					...request,
					resolve: ( confirmed: boolean ) => {
						pendingRef.current = null;
						setPending( null );
						resolve( confirmed );
					},
				};
				pendingRef.current = next;
				setPending( next );
			} );
		},
		[]
	);

	const confirmDialog = pending ? (
		<ConfirmDialog
			title={ pending.title }
			message={ pending.message }
			confirmLabel={ pending.confirmLabel }
			cancelLabel={ pending.cancelLabel }
			isDestructive={ pending.isDestructive }
			onConfirm={ () => pending.resolve( true ) }
			onCancel={ () => pending.resolve( false ) }
		/>
	) : null;

	return { requestConfirm, confirmDialog };
}
