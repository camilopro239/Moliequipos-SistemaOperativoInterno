import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface Toast {
  message: string;
  type: 'success' | 'error' | 'warning' | 'info';
  duration?: number;
}

@Injectable({
  providedIn: 'root',
})
export class ToastService {

  private toastSubject = new BehaviorSubject<Toast | null>(null);
  toast$ = this.toastSubject.asObservable();

  show(
    message: string,
    type: Toast['type'] = 'info',
    duration = 3000
  ) {
    this.toastSubject.next({ message, type, duration });

    setTimeout(() => {
      this.clear();
    }, duration);
  }

  success(message: string, duration?: number) {
    this.show(message, 'success', duration);
  }

  error(message: string, duration?: number) {
    this.show(message, 'error', duration);
  }

  warning(message: string, duration?: number) {
    this.show(message, 'warning', duration);
  }

  info(message: string, duration?: number) {
    this.show(message, 'info', duration);
  }

  clear() {
    this.toastSubject.next(null);
  }
}
